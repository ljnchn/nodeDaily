<?php

/**
 * Telegram Bot 测试脚本
 * 
 * 使用方法：
 * php scripts/test-telegram-bot.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;

// 加载环境变量
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

function testBot() {
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

    if (empty($botToken)) {
        echo "错误：TELEGRAM_BOT_TOKEN 环境变量未设置\n";
        return false;
    }

    try {
        $bot = new BotApi($botToken);
        
        echo "测试 Bot 连接...\n";
        
        // 获取 bot 信息
        $me = $bot->getMe();
        echo "✅ Bot 连接成功！\n";
        echo "Bot 用户名: @" . $me->getUsername() . "\n";
        echo "Bot 名称: " . $me->getFirstName() . "\n";
        echo "Bot ID: " . $me->getId() . "\n";
        
        // 获取更新
        echo "\n获取最新更新...\n";
        $updates = $bot->getUpdates();
        
        if (empty($updates)) {
            echo "暂无新消息\n";
        } else {
            echo "收到 " . count($updates) . " 条更新:\n";
            foreach ($updates as $update) {
                if ($update->getMessage()) {
                    $message = $update->getMessage();
                    $chatId = $message->getChat()->getId();
                    $text = $message->getText();
                    $from = $message->getFrom();
                    
                    echo "- 消息ID: " . $message->getMessageId() . "\n";
                    echo "  Chat ID: " . $chatId . "\n";
                    echo "  发送人: " . ($from->getUsername() ? '@' . $from->getUsername() : $from->getFirstName()) . "\n";
                    echo "  内容: " . $text . "\n\n";
                }
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 测试失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function sendTestMessage() {
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

    if (empty($botToken)) {
        echo "错误：TELEGRAM_BOT_TOKEN 环境变量未设置\n";
        return false;
    }

    echo "请输入要发送测试消息的 Chat ID: ";
    $chatId = trim(fgets(STDIN));
    
    if (empty($chatId) || !is_numeric($chatId)) {
        echo "错误：Chat ID 无效\n";
        return false;
    }

    try {
        $bot = new BotApi($botToken);
        
        $testMessage = "🤖 这是一条测试消息！\n\n";
        $testMessage .= "如果您收到这条消息，说明 Bot 工作正常。\n";
        $testMessage .= "发送时间: " . date('Y-m-d H:i:s');
        
        $result = $bot->sendMessage($chatId, $testMessage);
        
        if ($result) {
            echo "✅ 测试消息发送成功！\n";
            echo "消息ID: " . $result->getMessageId() . "\n";
        } else {
            echo "❌ 测试消息发送失败\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 发送测试消息失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function getBotCommands() {
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

    if (empty($botToken)) {
        echo "错误：TELEGRAM_BOT_TOKEN 环境变量未设置\n";
        return false;
    }

    try {
        $bot = new BotApi($botToken);
        
        echo "获取 Bot 命令列表...\n";
        $commands = $bot->getMyCommands();
        
        if (empty($commands)) {
            echo "未设置任何命令\n";
        } else {
            echo "当前设置的命令:\n";
            foreach ($commands as $command) {
                echo "- /" . $command->getCommand() . " - " . $command->getDescription() . "\n";
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 获取命令列表失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function setBotCommands() {
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

    if (empty($botToken)) {
        echo "错误：TELEGRAM_BOT_TOKEN 环境变量未设置\n";
        return false;
    }

    try {
        $bot = new BotApi($botToken);
        
        // 定义命令列表
        $commands = [
            ['command' => 'start', 'description' => '开始使用机器人'],
            ['command' => 'help', 'description' => '显示帮助信息'],
            ['command' => 'add_and', 'description' => '添加 AND 规则关键词'],
            ['command' => 'add_or', 'description' => '添加 OR 规则关键词'],
            ['command' => 'list', 'description' => '查看我的关键词订阅'],
            ['command' => 'delete', 'description' => '删除指定关键词订阅'],
        ];
        
        echo "设置 Bot 命令...\n";
        $result = $bot->setMyCommands($commands);
        
        if ($result) {
            echo "✅ Bot 命令设置成功！\n";
            echo "设置的命令:\n";
            foreach ($commands as $command) {
                echo "- /" . $command['command'] . " - " . $command['description'] . "\n";
            }
        } else {
            echo "❌ Bot 命令设置失败\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 设置命令失败: " . $e->getMessage() . "\n";
        return false;
    }
}

// 处理命令行参数
if ($argc > 1) {
    $command = $argv[1];
    
    switch ($command) {
        case 'info':
            testBot();
            break;
        case 'send':
            sendTestMessage();
            break;
        case 'commands':
            getBotCommands();
            break;
        case 'set-commands':
            setBotCommands();
            break;
        default:
            echo "使用方法:\n";
            echo "php scripts/test-telegram-bot.php info         - 获取 Bot 信息\n";
            echo "php scripts/test-telegram-bot.php send         - 发送测试消息\n";
            echo "php scripts/test-telegram-bot.php commands     - 获取命令列表\n";
            echo "php scripts/test-telegram-bot.php set-commands - 设置命令列表\n";
            break;
    }
} else {
    echo "使用方法:\n";
    echo "php scripts/test-telegram-bot.php info         - 获取 Bot 信息\n";
    echo "php scripts/test-telegram-bot.php send         - 发送测试消息\n";
    echo "php scripts/test-telegram-bot.php commands     - 获取命令列表\n";
    echo "php scripts/test-telegram-bot.php set-commands - 设置命令列表\n";
} 