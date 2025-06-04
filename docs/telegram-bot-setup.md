# Telegram Bot 设置指南

## 1. 创建 Telegram Bot

1. 在 Telegram 中找到 @BotFather
2. 发送 `/newbot` 命令
3. 按照提示设置 bot 名称和用户名
4. 获取 Bot Token

## 2. 环境变量配置

在项目根目录创建 `.env` 文件，添加以下配置：

```env
# Telegram Bot 配置
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://your-domain.com/telegram/webhook
```

## 3. 数据库设置

运行以下 SQL 脚本创建必要的数据表：

```bash
mysql -u your_username -p your_database < database/migrations/create_telegram_tables.sql
```

## 4. 设置 Webhook

在生产环境中，需要设置 Telegram Webhook：

```php
<?php
use TelegramBot\Api\BotApi;

$bot = new BotApi('YOUR_BOT_TOKEN');
$webhookUrl = 'https://your-domain.com/telegram/webhook';

try {
    $result = $bot->setWebhook($webhookUrl);
    if ($result) {
        echo "Webhook set successfully!\n";
    }
} catch (Exception $e) {
    echo "Error setting webhook: " . $e->getMessage() . "\n";
}
```

## 5. Bot 命令说明

### 用户命令
- `/start` - 开始使用机器人，注册用户
- `/help` - 显示帮助信息
- `/add_and <关键词>` - 添加 AND 规则关键词（多个用逗号分隔）
- `/add_or <关键词>` - 添加 OR 规则关键词（多个用逗号分隔）
- `/list` - 查看我的关键词订阅
- `/delete <ID>` - 删除指定关键词订阅

### 示例使用
```
/add_and PHP,Laravel
/add_or Python,Java,Go
/list
/delete 1
```

## 6. 匹配规则说明

- **AND 规则**：内容必须同时包含所有关键词
- **OR 规则**：内容包含任意一个关键词即可

## 7. 数据表结构

### telegram_users
存储 Telegram 用户信息

### keywords
存储关键词信息

### user_keyword_subscriptions
存储用户的关键词订阅关系

### keyword_matches
存储关键词匹配记录（用于通知推送）

## 8. 开发说明

### 主要文件
- `app/controller/TelegramBotController.php` - Bot 控制器
- `config/telegram.php` - Telegram 配置
- `config/route.php` - 路由配置

### 依赖包
- `telegram-bot/api` - Telegram Bot API 包装器

### 错误处理
所有错误都会记录到错误日志中，用户会收到友好的错误提示信息。 