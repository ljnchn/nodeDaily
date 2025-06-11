<?php

namespace app\controller;

use support\Request;
use support\Response;
use app\service\UserService;
use app\service\TelegramService;
use app\service\KeywordSubscriptionService;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

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

            // 处理按钮点击回调
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
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

            case '/del':
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
            
            // 创建欢迎消息的快捷操作按钮
            $welcomeKeyboard = $this->createMultiButtonKeyboard(['/add', '/list', '/help']);
            
            $welcomeMessage = "🎉 欢迎使用关键词订阅机器人！\n\n";
            $welcomeMessage .= "📝 使用 /add 添加关键词订阅\n";
            $welcomeMessage .= "📋 使用 /list 查看订阅列表\n";
            $welcomeMessage .= "🗑️ 使用 /del 删除订阅\n";
            $welcomeMessage .= "❓ 使用 /help 获取帮助\n\n";
            $welcomeMessage .= "点击下方按钮快速开始：";
            
            $this->telegramService->sendMessage($chatId, $welcomeMessage, $welcomeKeyboard);
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
            
            // 如果有订阅，显示添加和删除按钮；如果没有订阅，只显示添加按钮
            $listKeyboard = empty($subscriptions) 
                ? $this->createInlineKeyboard('/add') 
                : $this->createMultiButtonKeyboard(['/add', '/del']);
                
            $this->telegramService->sendMessage($chatId, $message, $listKeyboard);
        } catch (\Exception $e) {
            $this->telegramService->sendMessage($chatId, "获取关键词列表失败，请稍后重试。");
            error_log('Error listing keywords: ' . $e->getMessage());
        }
    }

    /**
     * 处理按钮点击回调
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $callbackData = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];
        $user = $callbackQuery['from'];

        // 应答回调查询（移除按钮上的加载状态）
        $this->telegramService->answerCallbackQuery($callbackQueryId);

        // 根据回调数据执行相应的命令
        switch ($callbackData) {
            case '/start':
                $this->handleStartCommand($chatId, $user);
                break;
            case '/add':
                $this->telegramService->sendMessage($chatId, "请输入要添加的关键词：\n\n格式：/add 关键词1 关键词2\n\n例如：/add PHP Laravel");
                break;
            case '/list':
                $this->handleListCommand($chatId);
                break;
            case '/del':
                $this->telegramService->sendMessage($chatId, "请输入要删除的订阅序号：\n\n格式：/del 序号\n\n先使用 /list 查看订阅列表获取序号");
                break;
            case '/help':
                $this->telegramService->sendHelpMessage($chatId);
                break;
            default:
                $this->telegramService->sendMessage($chatId, "未知操作");
                break;
        }
    }

    /**
     * 创建内联键盘按钮
     */
    private function createInlineKeyboard(string $command, $buttonText = null): InlineKeyboardMarkup
    {
        $buttonTexts = [
            '/add' => '📝 点击添加关键词',
            '/del' => '🗑️ 点击删除订阅',
            '/list' => '📋 查看订阅列表',
            '/help' => '❓ 获取帮助'
        ];

        $text = $buttonText ?? ($buttonTexts[$command] ?? '点击操作');
        
        return new InlineKeyboardMarkup([
            [
                [
                    'text' => $text,
                    'callback_data' => $command
                ]
            ]
        ]);
    }

    /**
     * 创建多个按钮的内联键盘
     */
    private function createMultiButtonKeyboard(array $commands): InlineKeyboardMarkup
    {
        $buttonTexts = [
            '/add' => '📝 添加',
            '/del' => '🗑️ 删除', 
            '/list' => '📋 列表',
            '/help' => '❓ 帮助'
        ];

        $buttons = [];
        foreach ($commands as $command) {
            $buttons[] = [
                'text' => $buttonTexts[$command] ?? '操作',
                'callback_data' => $command
            ];
        }

        return new InlineKeyboardMarkup([$buttons]);
    }

    /**
     * 处理delete命令
     */
    private function handleDeleteCommand(int $chatId, string $subscriptionIndex): void
    {
        if (empty($subscriptionIndex) || !is_numeric($subscriptionIndex)) {
            $keyboard = $this->createInlineKeyboard('/del');
            $this->telegramService->sendMessage($chatId, "请提供有效的订阅序号！\n\n使用 /list 查看您的订阅列表。", $keyboard);
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
        $keyboard = $this->createInlineKeyboard('/add');

        $keywords = trim($keywords);
        if (empty($keywords)) {
            $this->telegramService->sendMessage($chatId, "请提供关键词！", $keyboard);
            return;
        }
        
        // 单字符不允许订阅
        if (strlen($keywords) <= 1) {
            $this->telegramService->sendMessage($chatId, "单字符不允许订阅！", $keyboard);
            return;
        }

        try {
            $userId = $this->userService->getUserIdByChatId($chatId);

            $keywordsArray = explode(' ', $keywords);
            $keywordsArray = array_filter($keywordsArray, function ($keyword) {
                return !empty(trim($keyword));
            });
            
            if (empty($keywordsArray)) {
                $this->telegramService->sendMessage($chatId, "请提供关键词！", $keyboard);
                return;
            }
            
            if (count($keywordsArray) > 3) {
                $this->telegramService->sendMessage($chatId, "每条规则最多添加 3 个关键词！", $keyboard);
                return;
            }

            $success = $this->keywordSubscriptionService->subscribeKeywords($userId, $keywordsArray, $keywords);

            if ($success) {
                // 成功后显示操作按钮
                $successKeyboard = $this->createMultiButtonKeyboard(['/add', '/list']);
                $this->telegramService->sendMessage($chatId, "关键词添加成功！\n关键词：{$keywords}", $successKeyboard);
            } else {
                $this->telegramService->sendMessage($chatId, "关键词添加失败，请稍后重试。", $keyboard);
            }
        } catch (\Exception $e) {
            $this->telegramService->sendMessage($chatId, $e->getMessage(), $keyboard);
            error_log('Error adding keyword: ' . $e->getMessage());
        }
    }
}
