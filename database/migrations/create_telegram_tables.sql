-- 创建Telegram用户表
CREATE TABLE IF NOT EXISTS `telegram_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `chat_id` bigint(20) NOT NULL COMMENT 'Telegram Chat ID',
    `user_id` bigint(20) NOT NULL COMMENT 'Telegram User ID',
    `username` varchar(255) DEFAULT '' COMMENT '用户名',
    `first_name` varchar(255) DEFAULT '' COMMENT '名字',
    `last_name` varchar(255) DEFAULT '' COMMENT '姓氏',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_chat_id` (`chat_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Telegram用户表';

-- 创建关键词表（如果不存在）
CREATE TABLE IF NOT EXISTS `keywords` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `keyword_hash` varchar(32) NOT NULL COMMENT '关键词hash值',
    `keyword_text` text NOT NULL COMMENT '关键词内容',
    `keyword_type` varchar(20) DEFAULT 'single' COMMENT '关键词类型',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_keyword_hash` (`keyword_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='关键词表';

-- 创建用户关键词订阅表（如果不存在）
CREATE TABLE IF NOT EXISTS `user_keyword_subscriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `keyword_id` int(11) NOT NULL COMMENT '关键词ID',
    `match_rule` varchar(10) NOT NULL DEFAULT 'OR' COMMENT '匹配规则: AND, OR',
    `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否激活',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_keyword_id` (`keyword_id`),
    UNIQUE KEY `idx_user_keyword_rule` (`user_id`, `keyword_id`, `match_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户关键词订阅表';

-- 创建关键词匹配记录表（用于记录匹配到的内容）
CREATE TABLE IF NOT EXISTS `keyword_matches` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `keyword_id` int(11) NOT NULL COMMENT '关键词ID',
    `post_title` varchar(500) NOT NULL COMMENT '文章标题',
    `post_url` varchar(1000) NOT NULL COMMENT '文章链接',
    `post_content` text COMMENT '文章内容摘要',
    `feed_source` varchar(255) DEFAULT '' COMMENT 'RSS源',
    `matched_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '匹配时间',
    `notified_at` timestamp NULL COMMENT '通知时间',
    `is_notified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已通知',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_keyword_id` (`keyword_id`),
    KEY `idx_matched_at` (`matched_at`),
    KEY `idx_is_notified` (`is_notified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='关键词匹配记录表'; 