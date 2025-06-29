<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\TgPost;
use app\model\TgUsers;
use app\model\TgKeywordsSub;
use app\model\TgKeywords;
use app\model\TgPushLogs;
use app\service\TelegramService;
use app\service\UserService;

/**
 * NodeDaily 关键词推送命令
 * 
 * 用法：
 * php webman NodeDaily:keyWords push --limit=50    # 推送未推送的帖子给订阅用户
 * 
 * 功能：
 * 1. 读取 is_push = 0 的帖子
 * 2. 匹配用户订阅的关键词
 * 3. 发送 Telegram 消息给匹配的用户
 * 4. 记录推送日志
 * 5. 更新帖子的 is_push 状态
 */
class NodeDailyKeyWords extends Command
{
    protected static $defaultName = 'nodeDaily:keyWords';
    protected static $defaultDescription = 'NodeDaily keyWords push to subscribed users';

    /**
     * 锁文件句柄
     */
    private $lockHandle = null;

    /**
     * 锁文件路径
     */
    private $lockFile;

    public function __construct()
    {
        parent::__construct();
        $this->lockFile = runtime_path() . '/locks/nodedaily_keywords.lock';
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit posts to process', 50);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 尝试获取执行锁
        if (!$this->acquireLock($output)) {
            $output->writeln('<error>命令已在运行中，无法同时执行多个实例</error>');
            return self::FAILURE;
        }

        try {
            $limit = (int) $input->getOption('limit');
            $result = $this->pushToSubscribedUsers($output, $limit);
        } finally {
            // 确保释放锁
            $this->releaseLock($output);
        }

        return $result;
    }

    /**
     * 获取执行锁
     */
    private function acquireLock(OutputInterface $output): bool
    {
        try {
            // 确保锁文件目录存在
            $lockDir = dirname($this->lockFile);
            if (!is_dir($lockDir)) {
                if (!mkdir($lockDir, 0755, true)) {
                    $output->writeln('<error>无法创建锁文件目录: ' . $lockDir . '</error>');
                    return false;
                }
            }

            // 打开锁文件
            $this->lockHandle = fopen($this->lockFile, 'w');
            if ($this->lockHandle === false) {
                $output->writeln('<error>无法创建锁文件: ' . $this->lockFile . '</error>');
                return false;
            }

            // 尝试获取独占锁，非阻塞模式
            if (!flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
                fclose($this->lockHandle);
                $this->lockHandle = null;
                return false;
            }

            // 写入当前进程信息
            fwrite($this->lockHandle, "PID: " . getmypid() . "\n");
            fwrite($this->lockHandle, "Started: " . date('Y-m-d H:i:s') . "\n");
            fflush($this->lockHandle);

            $output->writeln('<info>✓ 获取执行锁成功</info>');
            return true;

        } catch (\Exception $e) {
            $output->writeln('<error>获取执行锁时发生错误: ' . $e->getMessage() . '</error>');
            if ($this->lockHandle) {
                fclose($this->lockHandle);
                $this->lockHandle = null;
            }
            return false;
        }
    }

    /**
     * 释放执行锁
     */
    private function releaseLock(OutputInterface $output): void
    {
        if ($this->lockHandle) {
            try {
                flock($this->lockHandle, LOCK_UN);
                fclose($this->lockHandle);
                $this->lockHandle = null;
                
                // 删除锁文件
                if (file_exists($this->lockFile)) {
                    unlink($this->lockFile);
                }
                
                $output->writeln('<info>✓ 执行锁已释放</info>');
            } catch (\Exception $e) {
                $output->writeln('<error>释放执行锁时发生错误: ' . $e->getMessage() . '</error>');
            }
        }
    }


    /**
     * 推送帖子给订阅用户
     */
    private function pushToSubscribedUsers(OutputInterface $output, int $limit): int
    {
        $output->writeln('开始处理未推送的帖子...');

        try {
            // 获取未推送的帖子 (handle = 0)
            $unpushedPosts = TgPost::where('handle', 0)
                ->limit($limit)
                ->orderBy('id', 'desc')
                ->get();

            if ($unpushedPosts->isEmpty()) {
                $output->writeln('没有找到未推送的帖子');
                return self::SUCCESS;
            }

            $output->writeln("找到 {$unpushedPosts->count()} 个未推送的帖子");

            // 获取所有活跃的关键词订阅
            $activeSubscriptions = TgKeywordsSub::where('is_active', 1)
                ->with(['user:id,chat_id,is_active'])
                ->get();

            if ($activeSubscriptions->isEmpty()) {
                $output->writeln('没有找到活跃的关键词订阅');
                return self::SUCCESS;
            }

            $telegramService = new TelegramService();
            $userService = new UserService();
            $pushedCount = 0;
            $errorCount = 0;
            $deactivatedUsers = 0;

            foreach ($unpushedPosts as $post) {
                $output->writeln("处理帖子: {$post->title}");

                // 根据帖子标题匹配关键词，获取匹配的关键词ID列表
                $matchedKeywordIds = $this->findMatchedKeywordIds($post);

                if (empty($matchedKeywordIds)) {
                    $output->writeln("帖子未匹配到任何关键词，跳过");
                    $post->handle = 1;
                    $post->save();
                    continue;
                }

                $output->writeln("匹配到关键词ID: " . implode(', ', $matchedKeywordIds));

                // 根据匹配的关键词ID找到相关的订阅用户
                $matchedSubscriptions = $this->findSubscriptionsByKeywordIds($matchedKeywordIds, $activeSubscriptions);

                // 确保每个帖子只推送一次给一个用户
                $postedUsers = [];
                foreach ($matchedSubscriptions as $subscriptionData) {

                    $subscription = $subscriptionData['subscription'];
                    $matchedKeywords = $subscriptionData['matched_keywords'];
                    $user = TgUsers::where('id', $subscription->user_id)->first();
                    if (!$user || !$user->is_active) {
                        continue;
                    }

                    if (in_array($user->chat_id, $postedUsers)) {
                        continue;
                    }

                    $postedUsers[] = $user->chat_id;

                    // 构建推送消息
                    $message = $this->buildPushMessage($post, $matchedKeywords);

                    // 发送消息
                    $result = $telegramService->sendMarkdownMessage($user->chat_id, $message);

                    // 记录推送日志
                    $this->logPushAttempt($user->id, $user->chat_id, $post->pid, $subscription->id, $result);

                    if ($result['success']) {
                        $pushedCount++;
                        $output->writeln("✓ 推送成功给用户 {$user->chat_id}");
                    } else {
                        $errorCount++;
                        $output->writeln("✗ 推送失败给用户 {$user->chat_id}: {$result['error_message']}");
                        
                        // 检查是否是用户停用bot的错误
                        if ($telegramService->isUserBlockedBot($result['error_code'] ?? 0, $result['error_message'] ?? '')) {
                            $output->writeln("⚠️ 检测到用户 {$user->chat_id} 停用了bot，正在停用用户状态...");
                            if ($userService->deactivateUser($user->chat_id)) {
                                $deactivatedUsers++;
                                $output->writeln("✓ 用户 {$user->chat_id} 状态已设为停用");
                            } else {
                                $output->writeln("✗ 无法更新用户 {$user->chat_id} 的状态");
                            }
                        }
                    }

                    // 避免频率限制，稍微延迟
                    usleep(100000); // 0.1秒
                }

                // 标记帖子为已推送
                $post->handle = 1;
                if ($post->save()) {
                    $output->writeln("✓ 帖子 {$post->pid} 已标记为已推送");
                } else {
                    $output->writeln("✗ 无法更新帖子 {$post->pid} 的状态");
                }
            }

            $output->writeln("推送完成！成功: $pushedCount, 失败: $errorCount, 停用用户: $deactivatedUsers");

        } catch (\Exception $e) {
            $output->writeln("错误: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 根据帖子标题匹配关键词，返回匹配的关键词ID列表
     */
    private function findMatchedKeywordIds(TgPost $post): array
    {
        $titleLower = strtolower($post->title);
        $descLower = strtolower($post->desc ?? '');

        // 只获取有订阅的关键词（sub_num > 0）
        $subscribedKeywords = TgKeywords::where('sub_num', '>', 0)->get();
        $matchedKeywordIds = [];

        foreach ($subscribedKeywords as $keyword) {
            $keywordLower = strtolower($keyword->keyword_text);

            // 主要匹配帖子标题，其次匹配描述
            if (
                strpos($titleLower, $keywordLower) !== false ||
                strpos($descLower, $keywordLower) !== false
            ) {
                $matchedKeywordIds[] = $keyword->id;
            }
        }

        return array_unique($matchedKeywordIds);
    }

    /**
     * 根据匹配的关键词ID找到相关的订阅用户
     */
    private function findSubscriptionsByKeywordIds(array $keywordIds, $activeSubscriptions): array
    {
        $matchedSubscriptions = [];

        foreach ($activeSubscriptions as $subscription) {
            // 获取用户订阅的关键词ID，保持订阅顺序
            $userKeywordIds = [];
            if ($subscription->keyword1_id) $userKeywordIds[] = $subscription->keyword1_id;
            if ($subscription->keyword2_id) $userKeywordIds[] = $subscription->keyword2_id;
            if ($subscription->keyword3_id) $userKeywordIds[] = $subscription->keyword3_id;

            if (empty($userKeywordIds)) {
                continue;
            }

            // 检查用户订阅的关键词是否与匹配的关键词ID有交集，保持订阅顺序
            $intersectedIds = [];
            foreach ($userKeywordIds as $userKeywordId) {
                if (in_array($userKeywordId, $keywordIds)) {
                    $intersectedIds[] = $userKeywordId;
                }
            }

            // 只有当用户订阅的所有关键词都匹配时才推送
            if (!empty($intersectedIds) && count($intersectedIds) == count($userKeywordIds)) {
                // 获取匹配的关键词文本，保持订阅顺序
                $matchedKeywords = [];
                foreach ($intersectedIds as $keywordId) {
                    $keyword = TgKeywords::find($keywordId);
                    if ($keyword) {
                        $matchedKeywords[] = $keyword->keyword_text;
                    }
                }

                $matchedSubscriptions[] = [
                    'subscription' => $subscription,
                    'matched_keywords' => $matchedKeywords,
                    'matched_keyword_ids' => $intersectedIds
                ];
            }
        }

        return $matchedSubscriptions;
    }



    /**
     * 构建推送消息
     */
    private function buildPushMessage(TgPost $post, array $matchedKeywords = []): string
    {
        $message = "🔔 关键词匹配通知：" . implode(', ', $matchedKeywords) . "\n\n";

        // 处理 $post->title 中的 () 和 []
        $title = str_replace(['(', ')', '[', ']'], ['（', '）', '【', '】'], $post->title);

        // 构建帖子链接
        $postUrl = "https://www.nodeseek.com/post-{$post->pid}-1";
        $message .= "[{$title}]({$postUrl})\n\n";

        return $message;
    }

    /**
     * 记录推送日志
     */
    private function logPushAttempt(int $userId, int $chatId, int $postId, int $subId, array $result): void
    {
        try {
            TgPushLogs::create([
                'user_id' => $userId,
                'chat_id' => $chatId,
                'post_id' => $postId,
                'sub_id' => $subId,
                'push_status' => $result['success'] ? 1 : 0,
                'error_message' => $result['success'] ? null : $result['error_message'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
            error_log("Failed to log push attempt: " . $e->getMessage());
        }
    }

}
