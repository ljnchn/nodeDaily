<?php

return [
    // Telegram Bot Token
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    
    // Webhook URL (需要在生产环境中设置)
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', ''),
    
    // Bot 设置
    'timeout' => 60,
    'max_connections' => 40,
    
    // 允许的更新类型
    'allowed_updates' => [
        'message',
        'callback_query',
    ],
]; 