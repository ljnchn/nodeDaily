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
            return $existing->id;
        }

        $keyword = TgKeywords::create([
            'keyword_hash' => $hash,
            'keyword_text' => $keywordText,
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
}
