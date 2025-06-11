<?php

namespace app\service;

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;

class TelegramService
{
    private $botApi;

    public function __construct()
    {
        $botToken = getenv('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            throw new \Exception('Telegram Bot Token not configured');
        }
        $this->botApi = new BotApi($botToken);
    }

    /**
     * 设置Webhook
     */
    public function setWebhook(string $webhookUrl): bool
    {
        try {
            $this->botApi->setWebhook($webhookUrl);
            return true;
        } catch (Exception $e) {
            error_log('Error setting webhook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 发送消息
     */
    public function sendMessage(int $chatId, string $text, $keyboard = null): bool
    {
        try {
            $this->botApi->sendMessage($chatId, $text, null, false, null, $keyboard);
            return true;
        } catch (Exception $e) {
            error_log('Error sending message: ' . $e->getMessage());
            return false;
        }
    }

    public function sendMarkdownMessage(int $chatId, string $text): bool
    {
        try {
            $this->botApi->sendMessage($chatId, $text, 'Markdown');
            return true;
        } catch (Exception $e) {
            error_log('Error sending markdown message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 发送帮助信息
     */
    public function sendHelpMessage(int $chatId): void
    {
        $help = "📖 使用帮助\n\n";
        $help .= "/start - 开始使用机器人\n";
        $help .= "`/add ` 关键词 - 添加关键词，多个用空格分隔\n";
        $help .= "/list - 查看我的关键词订阅\n";
        $help .= "`/del ` 序号 - 删除指定关键词订阅\n";
        $help .= "/help - 显示此帮助\n\n";
        $help .= "💡 示例：\n";
        $help .= "添加单个关键词 `/add ovh`\n";
        $help .= "添加多个关键词 `/add 出 ovh 0.97`\n";
        $help .= "⚠️ 注意：单个关键词不能包含空格，多个关键词用空格分隔\n";
        $help .= "服务器资源有限，每人最多订阅 5 条规则\n";

        $this->sendMarkdownMessage($chatId, $help);
    }

    /**
     * 发送欢迎消息
     */
    public function sendWelcomeMessage(int $chatId): void
    {
        $this->sendMessage($chatId, "欢迎使用 NodeDaily 关键词监控机器人！\n\n使用 /help 查看帮助");
    }

    /**
     * 应答回调查询（移除按钮上的加载状态）
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = null, bool $showAlert = false): bool
    {
        try {
            $this->botApi->answerCallbackQuery($callbackQueryId, $text, $showAlert);
            return true;
        } catch (Exception $e) {
            error_log('Error answering callback query: ' . $e->getMessage());
            return false;
        }
    }
} 