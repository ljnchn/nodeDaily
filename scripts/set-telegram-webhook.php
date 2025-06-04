<?php

/**
 * Telegram Webhook 设置脚本
 * 
 * 使用方法：
 * php scripts/set-telegram-webhook.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;

// 加载环境变量
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

function setWebhook() {
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    $webhookUrl = $_ENV['TELEGRAM_WEBHOOK_URL'] ?? '';

    if (empty($botToken)) {
        echo "错误：TELEGRAM_BOT_TOKEN 环境变量未设置\n";
        return false;
    }

    if (empty($webhookUrl)) {
        echo "错误：TELEGRAM_WEBHOOK_URL 环境变量未设置\n";
        return false;
    }

    try {
        $bot = new BotApi($botToken);
        
        echo "正在设置 Webhook...\n";
        echo "URL: {$webhookUrl}\n";
        
        $result = $bot->setWebhook($webhookUrl);
        
        if ($result) {
            echo "✅ Webhook 设置成功！\n";
        } else {
            echo "❌ Webhook 设置失败\n";
            return false;
        }
        
        // 获取 webhook 信息
        echo "\n获取 Webhook 信息...\n";
        $webhookInfo = $bot->getWebhookInfo();
        
        echo "Webhook URL: " . $webhookInfo->getUrl() . "\n";
        echo "待处理更新数: " . $webhookInfo->getPendingUpdateCount() . "\n";
        
        if ($webhookInfo->getLastErrorDate()) {
            echo "最后错误时间: " . date('Y-m-d H:i:s', $webhookInfo->getLastErrorDate()) . "\n";
            echo "最后错误信息: " . $webhookInfo->getLastErrorMessage() . "\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 设置 Webhook 时发生错误: " . $e->getMessage() . "\n";
        return false;
    }
}

function deleteWebhook() {
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

    if (empty($botToken)) {
        echo "错误：TELEGRAM_BOT_TOKEN 环境变量未设置\n";
        return false;
    }

    try {
        $bot = new BotApi($botToken);
        
        echo "正在删除 Webhook...\n";
        
        $result = $bot->deleteWebhook();
        
        if ($result) {
            echo "✅ Webhook 删除成功！\n";
        } else {
            echo "❌ Webhook 删除失败\n";
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 删除 Webhook 时发生错误: " . $e->getMessage() . "\n";
        return false;
    }
}

function getWebhookInfo() {
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

    if (empty($botToken)) {
        echo "错误：TELEGRAM_BOT_TOKEN 环境变量未设置\n";
        return false;
    }

    try {
        $bot = new BotApi($botToken);
        
        echo "获取 Webhook 信息...\n";
        $webhookInfo = $bot->getWebhookInfo();
        
        echo "Webhook URL: " . ($webhookInfo->getUrl() ?: '未设置') . "\n";
        echo "待处理更新数: " . $webhookInfo->getPendingUpdateCount() . "\n";
        echo "最大连接数: " . $webhookInfo->getMaxConnections() . "\n";
        
        if ($webhookInfo->getAllowedUpdates()) {
            echo "允许的更新类型: " . implode(', ', $webhookInfo->getAllowedUpdates()) . "\n";
        }
        
        if ($webhookInfo->getLastErrorDate()) {
            echo "最后错误时间: " . date('Y-m-d H:i:s', $webhookInfo->getLastErrorDate()) . "\n";
            echo "最后错误信息: " . $webhookInfo->getLastErrorMessage() . "\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 获取 Webhook 信息时发生错误: " . $e->getMessage() . "\n";
        return false;
    }
}

// 处理命令行参数
if ($argc > 1) {
    $command = $argv[1];
    
    switch ($command) {
        case 'set':
            setWebhook();
            break;
        case 'delete':
            deleteWebhook();
            break;
        case 'info':
            getWebhookInfo();
            break;
        default:
            echo "使用方法:\n";
            echo "php scripts/set-telegram-webhook.php set    - 设置 webhook\n";
            echo "php scripts/set-telegram-webhook.php delete - 删除 webhook\n";
            echo "php scripts/set-telegram-webhook.php info   - 获取 webhook 信息\n";
            break;
    }
} else {
    echo "使用方法:\n";
    echo "php scripts/set-telegram-webhook.php set    - 设置 webhook\n";
    echo "php scripts/set-telegram-webhook.php delete - 删除 webhook\n";
    echo "php scripts/set-telegram-webhook.php info   - 获取 webhook 信息\n";
} 