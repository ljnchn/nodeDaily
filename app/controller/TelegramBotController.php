<?php

namespace app\controller;

use support\Request;
use app\service\KeywordService;
use support\Response;
use support\Db;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;

class TelegramBotController
{
    private $keywordService;
    private $botApi;

    public function __construct()
    {
        $this->keywordService = new KeywordService();
        // 从环境变量获取Bot Token，如果没有则使用默认配置
        $botToken = getenv('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            throw new \Exception('Telegram Bot Token not configured');
        }
        $this->botApi = new BotApi($botToken);
    }

    public function setWebhook(Request $request): Response
    {
        $this->botApi->setWebhook(getenv('TELEGRAM_BOT_WEBHOOK_URL'));
        return response('OK');
    }

    public function webhook(Request $request): Response
    {
        try {
            $update = json_decode($request->rawBody(), true);

            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }

            return response('OK');
        } catch (\Exception $e) {
            // 记录错误日志
            error_log('Telegram webhook error: ' . $e->getMessage());
            return response('Error', 500);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if (strpos($text, '/') === 0) {
            $this->handleCommand($chatId, $text, $message['from']);
        }
    }

    private function handleCommand(int $chatId, string $command, array $user): void
    {
        $parts = explode(' ', $command, 2);
        $cmd = $parts[0];
        $params = $parts[1] ?? '';

        switch ($cmd) {
            case '/start':
                $this->registerUser($chatId, $user);
                $this->sendMessage($chatId, "欢迎使用 RSS 关键词监控机器人！\n\n使用 /help 查看帮助");
                break;

            case '/add_and':
                $this->addKeyword($chatId, $params, 'AND');
                break;

            case '/add_or':
                $this->addKeyword($chatId, $params, 'OR');
                break;

            case '/list':
                $this->listKeywords($chatId);
                break;

            case '/delete':
                $this->deleteKeyword($chatId, $params);
                break;

            case '/help':
                $this->sendHelp($chatId);
                break;

            default:
                $this->sendMessage($chatId, "未知命令，使用 /help 查看帮助");
                break;
        }
    }

    /**
     * 注册用户
     */
    private function registerUser(int $chatId, array $user): void
    {
        try {
            // 检查用户是否已存在
            $existingUser = Db::table('tg_users')
                ->where('chat_id', $chatId)
                ->first();

            if ($existingUser) {
                // 更新用户信息
                Db::table('tg_users')
                    ->where('chat_id', $chatId)
                    ->update([
                        'username' => $user['username'] ?? '',
                        'first_name' => $user['first_name'] ?? '',
                        'last_name' => $user['last_name'] ?? '',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // 创建新用户
                Db::table('tg_users')->insert([
                    'chat_id' => $chatId,
                    'username' => $user['username'] ?? '',
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (\Exception $e) {
            error_log('Error registering user: ' . $e->getMessage());
        }
    }

    /**
     * 发送消息
     */
    private function sendMessage(int $chatId, string $text): void
    {
        try {
            $this->botApi->sendMessage($chatId, $text);
        } catch (Exception $e) {
            error_log('Error sending message: ' . $e->getMessage());
        }
    }

    /**
     * 获取用户ID
     */
    private function getUserId(int $chatId): int
    {
        $user = Db::table('tg_users')
            ->where('chat_id', $chatId)
            ->first();

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user['id'];
    }

    /**
     * 列出用户的关键词订阅
     */
    private function listKeywords(int $chatId): void
    {
        try {
            $userId = $this->getUserId($chatId);

            $subscriptions = Db::table('tg_keyword_subscriptions as s')
                ->join('keywords as k', 's.keyword_id', '=', 'k.id')
                ->where('s.user_id', $userId)
                ->select('s.id', 'k.keyword_text', 's.match_rule', 's.created_at')
                ->get();

            if (empty($subscriptions)) {
                $this->sendMessage($chatId, "您还没有订阅任何关键词。\n\n使用 /add_and 或 /add_or 来添加关键词订阅。");
                return;
            }

            $message = "📋 您的关键词订阅列表：\n\n";
            foreach ($subscriptions as $sub) {
                $message .= "🔹 ID: {$sub['id']}\n";
                $message .= "   关键词: {$sub['keyword_text']}\n";
                $message .= "   规则: {$sub['match_rule']}\n";
                $message .= "   创建时间: {$sub['created_at']}\n\n";
            }

            $message .= "💡 使用 /delete <ID> 删除订阅";

            $this->sendMessage($chatId, $message);
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "获取关键词列表失败，请稍后重试。");
            error_log('Error listing keywords: ' . $e->getMessage());
        }
    }

    /**
     * 删除关键词订阅
     */
    private function deleteKeyword(int $chatId, string $subscriptionId): void
    {
        if (empty($subscriptionId) || !is_numeric($subscriptionId)) {
            $this->sendMessage($chatId, "请提供有效的订阅ID！\n\n使用 /list 查看您的订阅列表。");
            return;
        }

        try {
            $userId = $this->getUserId($chatId);

            // 检查订阅是否存在且属于该用户
            $subscription = Db::table('user_keyword_subscriptions')
                ->where('id', $subscriptionId)
                ->where('user_id', $userId)
                ->first();

            if (!$subscription) {
                $this->sendMessage($chatId, "未找到指定的订阅ID或该订阅不属于您。");
                return;
            }

            // 删除订阅
            $deleted = Db::table('user_keyword_subscriptions')
                ->where('id', $subscriptionId)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted) {
                $this->sendMessage($chatId, "关键词订阅删除成功！");
            } else {
                $this->sendMessage($chatId, "删除失败，请稍后重试。");
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "删除关键词订阅失败，请稍后重试。");
            error_log('Error deleting keyword: ' . $e->getMessage());
        }
    }

    private function addKeyword(int $chatId, string $keywords, string $rule): void
    {
        if (empty($keywords)) {
            $this->sendMessage($chatId, "请提供关键词！");
            return;
        }

        try {
            $userId = $this->getUserId($chatId);

            // 检查是否存在重复订阅
            $keywordHash = md5($keywords);
            $existing = Db::table('user_keyword_subscriptions as s')
                ->join('keywords as k', 's.keyword_id', '=', 'k.id')
                ->where('s.user_id', $userId)
                ->where('k.keyword_hash', $keywordHash)
                ->where('s.match_rule', $rule)
                ->first();

            if ($existing) {
                $this->sendMessage($chatId, "该关键词订阅已存在！");
                return;
            }

            $success = $this->keywordService->subscribeKeyword($userId, $keywords, $rule);

            if ($success) {
                $this->sendMessage($chatId, "关键词添加成功！\n规则：{$rule}\n关键词：{$keywords}");
            } else {
                $this->sendMessage($chatId, "关键词添加失败，请稍后重试。");
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "添加关键词失败，请稍后重试。");
            error_log('Error adding keyword: ' . $e->getMessage());
        }
    }

    private function sendHelp(int $chatId): void
    {
        $help = "📖 使用帮助\n\n";
        $help .= "/start - 开始使用机器人\n";
        $help .= "/add_and <关键词> - 添加 AND 规则关键词（多个用逗号分隔）\n";
        $help .= "/add_or <关键词> - 添加 OR 规则关键词（多个用逗号分隔）\n";
        $help .= "/list - 查看我的关键词订阅\n";
        $help .= "/delete <ID> - 删除指定关键词订阅\n";
        $help .= "/help - 显示此帮助\n\n";
        $help .= "💡 示例：\n";
        $help .= "/add_and PHP,Laravel - 同时包含 PHP 和 Laravel\n";
        $help .= "/add_or Python,Java - 包含 Python 或 Java\n\n";
        $help .= "📝 说明：\n";
        $help .= "• AND 规则：内容必须同时包含所有关键词\n";
        $help .= "• OR 规则：内容包含任意一个关键词即可";

        $this->sendMessage($chatId, $help);
    }
}
