<?php

namespace app\service;

use app\model\TgKeywords;

class KeywordService
{
    /**
     * 添加关键词（避免重复存储）
     */
    public function addKeyword(string $keywordText): int
    {
        $hash = md5($keywordText);

        // 检查是否已存在
        $existing = TgKeywords::where('keyword_hash', $hash)->first();

        if ($existing) {
            $existing->sub_num += 1;
            $existing->save();
            return $existing->id;
        }

        $keyword = TgKeywords::create([
            'keyword_hash' => $hash,
            'keyword_text' => $keywordText,
            'sub_num' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $keyword->id;
    }

    /**
     * 获取关键词
     */
    public function getKeyword(int $keywordId): ?TgKeywords
    {
        return TgKeywords::find($keywordId);
    }

    /**
     * 根据文本获取关键词
     */
    public function getKeywordByText(string $keywordText): ?TgKeywords
    {
        $hash = md5($keywordText);
        return TgKeywords::where('keyword_hash', $hash)->first();
    }

    /**
     * 批量增加关键词订阅数量
     */
    public function incrementSubNumBatch(array $keywordIds): bool
    {
        if (empty($keywordIds)) {
            return true;
        }
        
        return TgKeywords::whereIn('id', $keywordIds)->increment('sub_num');
    }

    /**
     * 批量减少关键词订阅数量
     */
    public function decrementSubNumBatch(array $keywordIds): bool
    {
        if (empty($keywordIds)) {
            return true;
        }
        
        return TgKeywords::whereIn('id', $keywordIds)
            ->where('sub_num', '>', 0)
            ->decrement('sub_num');
    }
}
