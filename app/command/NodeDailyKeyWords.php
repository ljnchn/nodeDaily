<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;
use app\model\TgUsers;
use app\model\TgKeywordsSub;
use app\model\TgKeywords;
use app\model\TgPushLogs;
use app\service\TelegramService;

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
    protected static $defaultName = 'NodeDaily:keyWords';
    protected static $defaultDescription = 'NodeDaily keyWords push to subscribed users';

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
        $limit = (int) $input->getOption('limit');
        return $this->pushToSubscribedUsers($output, $limit);
    }


    /**
     * 推送帖子给订阅用户
     */
    private function pushToSubscribedUsers(OutputInterface $output, int $limit): int
    {
        $output->writeln('开始处理未推送的帖子...');

        try {
            // 获取未推送的帖子 (is_push = 0)
            $unpushedPosts = Post::where('is_push', 0)
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
            $pushedCount = 0;
            $errorCount = 0;

            foreach ($unpushedPosts as $post) {
                $output->writeln("处理帖子: {$post->title}");

                // 根据帖子标题匹配关键词，获取匹配的关键词ID列表
                $matchedKeywordIds = $this->findMatchedKeywordIds($post);

                if (empty($matchedKeywordIds)) {
                    $output->writeln("帖子未匹配到任何关键词，跳过");
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
                    $success = $telegramService->sendMarkdownMessage($user->chat_id, $message);

                    // 记录推送日志
                    $this->logPushAttempt($user->id, $user->chat_id, $post->id, $subscription->id, $success);

                    if ($success) {
                        $pushedCount++;
                        $output->writeln("✓ 推送成功给用户 {$user->chat_id}");
                    } else {
                        $errorCount++;
                        $output->writeln("✗ 推送失败给用户 {$user->chat_id}");
                    }

                    // 避免频率限制，稍微延迟
                    usleep(100000); // 0.1秒
                }

                // 标记帖子为已推送
                $post->is_push = 1;
                $post->save();
            }

            $output->writeln("推送完成！成功: $pushedCount, 失败: $errorCount");

        } catch (\Exception $e) {
            $output->writeln("错误: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 根据帖子标题匹配关键词，返回匹配的关键词ID列表
     */
    private function findMatchedKeywordIds(Post $post): array
    {
        $titleLower = strtolower($post->title);
        $descLower = strtolower($post->desc ?? '');

        // 获取所有关键词
        $allKeywords = TgKeywords::all();
        $matchedKeywordIds = [];

        foreach ($allKeywords as $keyword) {
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
            // 获取用户订阅的关键词ID
            $userKeywordIds = array_filter([
                $subscription->keyword1_id,
                $subscription->keyword2_id,
                $subscription->keyword3_id
            ]);

            if (empty($userKeywordIds)) {
                continue;
            }

            // 检查用户订阅的关键词是否与匹配的关键词
            $intersectedIds = array_intersect($userKeywordIds, $keywordIds);

            if (!empty($intersectedIds) && count($intersectedIds) == count($userKeywordIds)) {
                // 获取匹配的关键词文本
                $matchedKeywords = TgKeywords::whereIn('id', $intersectedIds)
                    ->pluck('keyword_text')
                    ->toArray();

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
    private function buildPushMessage(Post $post, array $matchedKeywords = []): string
    {
        $message = "🔔 关键词匹配通知：" . implode(', ', $matchedKeywords) . "\n\n";

        // 处理 $post->title 中的 () 和 []
        $title = str_replace(['(', ')', '[', ']'], ['（', '）', '【', '】'], $post->title);

        // 构建帖子链接
        $postUrl = "https://www.nodeseek.com/post-{$post->id}-1";
        $message .= "[{$title}]({$postUrl})\n\n";

        return $message;
    }

    /**
     * 记录推送日志
     */
    private function logPushAttempt(int $userId, int $chatId, int $postId, int $subId, bool $success): void
    {
        try {
            TgPushLogs::create([
                'user_id' => $userId,
                'chat_id' => $chatId,
                'post_id' => $postId,
                'sub_id' => $subId,
                'push_status' => $success ? 1 : 0,
                'error_message' => $success ? null : 'Failed to send message',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
            error_log("Failed to log push attempt: " . $e->getMessage());
        }
    }

}
