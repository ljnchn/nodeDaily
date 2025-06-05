<?php

namespace app\service;

use support\Db;

class KeywordService
{
    /**
     * 添加关键词（避免重复存储）
     */
    public function addKeyword(string $keywordText): int
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
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 用户订阅关键词
     */
    public function subscribeKeyword(int $userId, array $keywordsArray): bool
    {
        foreach ($keywordsArray as $keyword) {
            $keywordId = $this->addKeyword($keyword);
            if (!$keywordId) {
                return false;
            }
            $keywordIds[] = $keywordId;
        }

        return Db::table('tg_keywords_sub')->insert([
            'user_id' => $userId,
            'keyword1_id' => $keywordIds[0] ?? 0,
            'keyword2_id' => $keywordIds[1] ?? 0,
            'keyword3_id' => $keywordIds[2] ?? 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
