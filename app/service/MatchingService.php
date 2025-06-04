<?php

namespace app\service;

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
