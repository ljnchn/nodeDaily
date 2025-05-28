我来为您设计一个基于 PHP Webman 的 RSS 监控和 Telegram 推送系统的技术方案。

## 1. 系统架构设计

### 1.1 整体架构
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Telegram Bot  │◄──►│   Webman API    │◄──►│     MySQL       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                              │
                              ▼
                       ┌─────────────────┐
                       │  RSS 监控服务    │
                       └─────────────────┘
```

### 1.2 核心模块
- **Telegram Bot 交互模块**：处理用户命令和关键词管理
- **RSS 监控模块**：定时抓取 RSS 源
- **关键词匹配引擎**：高效匹配算法
- **消息推送模块**：Telegram 消息发送
- **用户管理模块**：用户状态和权限管理

## 2. 数据库设计

### 2.1 用户表 (users)
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    telegram_user_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_telegram_user_id (telegram_user_id)
);
```

### 2.2 关键词表 (keywords)
```sql
CREATE TABLE keywords (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    keyword_hash VARCHAR(64) UNIQUE NOT NULL COMMENT 'MD5哈希，避免重复存储',
    keyword_text TEXT NOT NULL COMMENT '原始关键词文本',
    keyword_type ENUM('single', 'and_group', 'or_group') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_keyword_hash (keyword_hash),
    INDEX idx_keyword_type (keyword_type)
);
```

### 2.3 用户关键词订阅表 (user_keyword_subscriptions)
```sql
CREATE TABLE user_keyword_subscriptions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    keyword_id BIGINT NOT NULL,
    match_rule ENUM('AND', 'OR') NOT NULL COMMENT '匹配规则',
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_keyword (user_id, keyword_id),
    INDEX idx_user_id (user_id),
    INDEX idx_keyword_id (keyword_id),
    INDEX idx_match_rule (match_rule)
);
```

### 2.4 RSS 源表 (rss_feeds)
```sql
CREATE TABLE rss_feeds (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    last_check_time TIMESTAMP NULL,
    last_item_guid VARCHAR(255),
    is_active TINYINT DEFAULT 1,
    check_interval INT DEFAULT 300 COMMENT '检查间隔(秒)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_last_check_time (last_check_time)
);
```

### 2.5 RSS 文章表 (rss_items)
```sql
CREATE TABLE rss_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    feed_id BIGINT NOT NULL,
    guid VARCHAR(255) NOT NULL,
    title TEXT NOT NULL,
    link TEXT,
    description TEXT,
    pub_date TIMESTAMP,
    processed TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_feed_guid (feed_id, guid),
    INDEX idx_feed_id (feed_id),
    INDEX idx_processed (processed),
    INDEX idx_pub_date (pub_date)
);
```

### 2.6 推送记录表 (push_logs)
```sql
CREATE TABLE push_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    rss_item_id BIGINT NOT NULL,
    keyword_id BIGINT NOT NULL,
    match_rule ENUM('AND', 'OR') NOT NULL,
    push_status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_rss_item_id (rss_item_id),
    INDEX idx_push_status (push_status),
    INDEX idx_created_at (created_at)
);
```

## 3. 核心代码实现

### 3.1 关键词管理服务
```php
<?php
namespace App\Service;

class KeywordService
{
    /**
     * 添加关键词（避免重复存储）
     */
    public function addKeyword(string $keywordText, string $type = 'single'): int
    {
        $hash = md5($keywordText);
        
        // 检查是否已存在
        $existing = Db::table('keywords')
            ->where('keyword_hash', $hash)
            ->first();
            
        if ($existing) {
            return $existing['id'];
        }
        
        return Db::table('keywords')->insertGetId([
            'keyword_hash' => $hash,
            'keyword_text' => $keywordText,
            'keyword_type' => $type,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 用户订阅关键词
     */
    public function subscribeKeyword(int $userId, string $keywordText, string $matchRule): bool
    {
        $keywordId = $this->addKeyword($keywordText);
        
        return Db::table('user_keyword_subscriptions')->insert([
            'user_id' => $userId,
            'keyword_id' => $keywordId,
            'match_rule' => $matchRule,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
```

### 3.2 高效关键词匹配算法
```php
<?php
namespace App\Service;

class MatchingService
{
    /**
     * 高效关键词匹配算法
     */
    public function matchKeywords(string $title, array $userSubscriptions): array
    {
        $matches = [];
        
        // 预处理标题（转小写，去除特殊字符）
        $normalizedTitle = $this->normalizeText($title);
        
        foreach ($userSubscriptions as $subscription) {
            $keyword = $subscription['keyword_text'];
            $matchRule = $subscription['match_rule'];
            $keywordType = $subscription['keyword_type'];
            
            $isMatch = false;
            
            switch ($keywordType) {
                case 'single':
                    $isMatch = $this->matchSingle($normalizedTitle, $keyword);
                    break;
                    
                case 'and_group':
                    $isMatch = $this->matchAndGroup($normalizedTitle, $keyword);
                    break;
                    
                case 'or_group':
                    $isMatch = $this->matchOrGroup($normalizedTitle, $keyword);
                    break;
            }
            
            if ($isMatch) {
                $matches[] = $subscription;
            }
        }
        
        return $this->applyMatchRules($matches);
    }
    
    private function normalizeText(string $text): string
    {
        return strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text));
    }
    
    private function matchSingle(string $title, string $keyword): bool
    {
        $normalizedKeyword = $this->normalizeText($keyword);
        return strpos($title, $normalizedKeyword) !== false;
    }
    
    private function matchAndGroup(string $title, string $keywords): bool
    {
        $keywordArray = explode(',', $keywords);
        foreach ($keywordArray as $keyword) {
            if (!$this->matchSingle($title, trim($keyword))) {
                return false;
            }
        }
        return true;
    }
    
    private function matchOrGroup(string $title, string $keywords): bool
    {
        $keywordArray = explode(',', $keywords);
        foreach ($keywordArray as $keyword) {
            if ($this->matchSingle($title, trim($keyword))) {
                return true;
            }
        }
        return false;
    }
    
    private function applyMatchRules(array $matches): array
    {
        // 按用户分组
        $userMatches = [];
        foreach ($matches as $match) {
            $userId = $match['user_id'];
            $matchRule = $match['match_rule'];
            
            if (!isset($userMatches[$userId])) {
                $userMatches[$userId] = ['AND' => [], 'OR' => []];
            }
            
            $userMatches[$userId][$matchRule][] = $match;
        }
        
        $finalMatches = [];
        foreach ($userMatches as $userId => $rules) {
            // AND 规则：所有关键词都必须匹配
            if (!empty($rules['AND'])) {
                $finalMatches[$userId] = $rules['AND'];
            }
            
            // OR 规则：任一关键词匹配即可
            if (!empty($rules['OR'])) {
                if (!isset($finalMatches[$userId])) {
                    $finalMatches[$userId] = [];
                }
                $finalMatches[$userId] = array_merge($finalMatches[$userId], $rules['OR']);
            }
        }
        
        return $finalMatches;
    }
}
```

### 3.3 RSS 监控服务
```php
<?php
namespace App\Service;

use Workerman\Timer;

class RssMonitorService
{
    private $matchingService;
    private $telegramService;
    
    public function __construct()
    {
        $this->matchingService = new MatchingService();
        $this->telegramService = new TelegramService();
    }
    
    /**
     * 启动 RSS 监控
     */
    public function startMonitoring(): void
    {
        // 每5分钟检查一次
        Timer::add(300, function() {
            $this->checkAllFeeds();
        });
    }
    
    private function checkAllFeeds(): void
    {
        $feeds = Db::table('rss_feeds')
            ->where('is_active', 1)
            ->get();
            
        foreach ($feeds as $feed) {
            $this->processFeed($feed);
        }
    }
    
    private function processFeed(array $feed): void
    {
        try {
            $rssContent = file_get_contents($feed['url']);
            $xml = simplexml_load_string($rssContent);
            
            foreach ($xml->channel->item as $item) {
                $guid = (string)$item->guid ?: (string)$item->link;
                
                // 检查是否已处理
                $exists = Db::table('rss_items')
                    ->where('feed_id', $feed['id'])
                    ->where('guid', $guid)
                    ->exists();
                    
                if (!$exists) {
                    $this->processNewItem($feed['id'], $item);
                }
            }
            
            // 更新最后检查时间
            Db::table('rss_feeds')
                ->where('id', $feed['id'])
                ->update(['last_check_time' => date('Y-m-d H:i:s')]);
                
        } catch (\Exception $e) {
            Log::error("RSS feed processing failed: " . $e->getMessage());
        }
    }
    
    private function processNewItem(int $feedId, $item): void
    {
        $title = (string)$item->title;
        $link = (string)$item->link;
        $guid = (string)$item->guid ?: $link;
        
        // 保存新文章
        $itemId = Db::table('rss_items')->insertGetId([
            'feed_id' => $feedId,
            'guid' => $guid,
            'title' => $title,
            'link' => $link,
            'description' => (string)$item->description,
            'pub_date' => date('Y-m-d H:i:s', strtotime((string)$item->pubDate)),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 获取所有用户订阅
        $subscriptions = $this->getUserSubscriptions();
        
        // 匹配关键词
        $matches = $this->matchingService->matchKeywords($title, $subscriptions);
        
        // 发送推送
        foreach ($matches as $userId => $userMatches) {
            $this->telegramService->sendNotification($userId, $title, $link, $userMatches);
        }
    }
    
    private function getUserSubscriptions(): array
    {
        return Db::table('user_keyword_subscriptions as uks')
            ->join('keywords as k', 'uks.keyword_id', '=', 'k.id')
            ->join('users as u', 'uks.user_id', '=', 'u.id')
            ->where('uks.is_active', 1)
            ->where('u.is_active', 1)
            ->select([
                'uks.user_id',
                'uks.match_rule',
                'k.keyword_text',
                'k.keyword_type',
                'k.id as keyword_id',
                'u.telegram_user_id'
            ])
            ->get()
            ->toArray();
    }
}
```

### 3.4 Telegram Bot 控制器
```php
<?php
namespace App\Controller;

class TelegramBotController
{
    private $keywordService;
    
    public function __construct()
    {
        $this->keywordService = new KeywordService();
    }
    
    public function webhook(Request $request): Response
    {
        $update = json_decode($request->rawBody(), true);
        
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
        
        return response('OK');
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
        }
    }
    
    private function addKeyword(int $chatId, string $keywords, string $rule): void
    {
        if (empty($keywords)) {
            $this->sendMessage($chatId, "请提供关键词！");
            return;
        }
        
        $userId = $this->getUserId($chatId);
        $success = $this->keywordService->subscribeKeyword($userId, $keywords, $rule);
        
        if ($success) {
            $this->sendMessage($chatId, "关键词添加成功！\n规则：{$rule}\n关键词：{$keywords}");
        } else {
            $this->sendMessage($chatId, "关键词添加失败，可能已存在相同订阅。");
        }
    }
    
    private function sendHelp(int $chatId): void
    {
        $help = "📖 使用帮助\n\n";
        $help .= "/add_and <关键词> - 添加 AND 规则关键词（多个用逗号分隔）\n";
        $help .= "/add_or <关键词> - 添加 OR 规则关键词（多个用逗号分隔）\n";
        $help .= "/list - 查看我的关键词订阅\n";
        $help .= "/delete <ID> - 删除指定关键词订阅\n";
        $help .= "/help - 显示此帮助\n\n";
        $help .= "💡 示例：\n";
        $help .= "/add_and PHP,Laravel - 同时包含 PHP 和 Laravel\n";
        $help .= "/add_or Python,Java - 包含 Python 或 Java";
        
        $this->sendMessage($chatId, $help);
    }
}
```

## 4. 性能优化策略

### 4.1 数据库优化
- 使用适当的索引提高查询效率
- 关键词哈希避免重复存储
- 分页查询大量数据
- 定期清理过期数据

### 4.2 匹配算法优化
- 文本预处理和标准化
- 使用字符串匹配优化算法
- 缓存常用关键词匹配结果
- 批量处理减少数据库查询

### 4.3 系统架构优化
- 使用 Redis 缓存热点数据
- 异步处理 RSS 抓取和推送
- 队列处理高并发推送
- 监控和日志记录

## 5. 部署和运维

### 5.1 Webman 配置
```php
// config/server.php
return [
    'listen' => 'http://0.0.0.0:8787',
    'transport' => 'tcp',
    'context' => [],
    'name' => 'webman',
    'count' => cpu_count() * 4,
    'user' => '',
    'group' => '',
    'reusePort' => false,
    'event_loop' => '',
    'stop_timeout' => 2,
    'pid_file' => runtime_path() . '/webman.pid',
    'status_file' => runtime_path() . '/webman.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'log_file' => runtime_path() . '/logs/workerman.log',
    'max_package_size' => 10 * 1024 * 1024
];
```

### 5.2 定时任务配置
```php
// config/process.php
return [
    'rss-monitor' => [
        'handler' => App\Process\RssMonitorProcess::class,
        'count' => 1,
        'constructor' => []
    ]
];
```

这个技术方案提供了完整的 RSS 监控和 Telegram 推送系统，具有高效的关键词匹配算法、灵活的用户管理和良好的扩展性。
