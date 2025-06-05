<?php

namespace app\controller;

use support\Request;
use support\Response;
use app\service\UserService;
use app\service\TelegramService;
use app\service\KeywordSubscriptionService;

class TelegramBotController
{
    private $userService;
    private $telegramService;
    private $keywordSubscriptionService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->telegramService = new TelegramService();
        $this->keywordSubscriptionService = new KeywordSubscriptionService();
    }

    public function setWebhook(Request $request): Response
    {
        $webhookUrl = getenv('TELEGRAM_BOT_WEBHOOK_URL');
        $success = $this->telegramService->setWebhook($webhookUrl);
        return response($success ? 'OK' : 'Failed', $success ? 200 : 500);
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
                $this->handleStartCommand($chatId, $user);
                break;

            case '/add':
                $this->handleAddCommand($chatId, $params);
                break;

            case '/list':
                $this->handleListCommand($chatId);
                break;

            case '/delete':
                $this->handleDeleteCommand($chatId, $params);
                break;

            case '/help':
                $this->telegramService->sendHelpMessage($chatId);
                break;

            default:
                $this->telegramService->sendMessage($chatId, "未知命令，使用 /help 查看帮助");
                break;
        }
    }

    /**
     * 处理start命令
     */
    private function handleStartCommand(int $chatId, array $user): void
    {
        try {
            $this->userService->registerOrUpdateUser($chatId, $user);
            $this->telegramService->sendWelcomeMessage($chatId);
        } catch (\Exception $e) {
            error_log('Error handling start command: ' . $e->getMessage());
            $this->telegramService->sendMessage($chatId, "注册失败，请稍后重试。");
        }
    }

    /**
     * 处理list命令
     */
    private function handleListCommand(int $chatId): void
    {
        try {
            $userId = $this->userService->getUserIdByChatId($chatId);
            $subscriptions = $this->keywordSubscriptionService->getUserKeywordSubscriptions($userId);
            $message = $this->keywordSubscriptionService->formatSubscriptionsMessage($subscriptions);
            $this->telegramService->sendMessage($chatId, $message);
        } catch (\Exception $e) {
            $this->telegramService->sendMessage($chatId, "获取关键词列表失败，请稍后重试。");
            error_log('Error listing keywords: ' . $e->getMessage());
        }
    }

    /**
     * 处理delete命令
     */
    private function handleDeleteCommand(int $chatId, string $subscriptionIndex): void
    {
        if (empty($subscriptionIndex) || !is_numeric($subscriptionIndex)) {
            $this->telegramService->sendMessage($chatId, "请提供有效的订阅序号！\n\n使用 /list 查看您的订阅列表。");
            return;
        }

        try {
            $userId = $this->userService->getUserIdByChatId($chatId);
            $success = $this->keywordSubscriptionService->deleteSubscription($userId, (int)$subscriptionIndex);

            if ($success) {
                $this->telegramService->sendMessage($chatId, "关键词订阅删除成功！");
            } else {
                $this->telegramService->sendMessage($chatId, "未找到指定的订阅序号或序号无效。请使用 /list 查看正确的序号。");
            }
        } catch (\Exception $e) {
            $this->telegramService->sendMessage($chatId, "删除关键词订阅失败，请稍后重试。");
            error_log('Error deleting keyword: ' . $e->getMessage());
        }
    }

    /**
     * 处理add命令
     */
    private function handleAddCommand(int $chatId, string $keywords): void
    {
        $keywords = trim($keywords);
        if (empty($keywords)) {
            $this->telegramService->sendMessage($chatId, "请提供关键词！");
            return;
        }

        try {
            $userId = $this->userService->getUserIdByChatId($chatId);

            $keywordsArray = explode(' ', $keywords);
            $keywordsArray = array_filter($keywordsArray, function ($keyword) {
                return !empty(trim($keyword));
            });
            
            if (empty($keywordsArray)) {
                $this->telegramService->sendMessage($chatId, "请提供关键词！");
                return;
            }
            
            if (count($keywordsArray) > 3) {
                $this->telegramService->sendMessage($chatId, "每条规则最多添加 3 个关键词！");
                return;
            }

            $success = $this->keywordSubscriptionService->subscribeKeywords($userId, $keywordsArray, $keywords);

            if ($success) {
                $this->telegramService->sendMessage($chatId, "关键词添加成功！\n关键词：{$keywords}");
            } else {
                $this->telegramService->sendMessage($chatId, "关键词添加失败，请稍后重试。");
            }
        } catch (\Exception $e) {
            $this->telegramService->sendMessage($chatId, $e->getMessage());
            error_log('Error adding keyword: ' . $e->getMessage());
        }
    }
}
