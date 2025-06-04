<?php

namespace app\service;

use support\Db;

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
