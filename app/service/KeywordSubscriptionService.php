<?php

namespace app\service;

use app\model\TgKeywordsSub;
use app\model\TgKeywords;

class KeywordSubscriptionService
{
    private $keywordService;

    public function __construct()
    {
        $this->keywordService = new KeywordService();
    }

    /**
     * 获取用户的关键词订阅列表
     */
    public function getUserKeywordSubscriptions(int $userId): array
    {
        $subscriptions = TgKeywordsSub::where('user_id', $userId)
            ->where('is_active', 1)
            ->get();

        if ($subscriptions->isEmpty()) {
            return [];
        }

        // 获取所有关键词ID
        $keywordIds = [];
        foreach ($subscriptions as $sub) {
            if ($sub->keyword1_id) $keywordIds[] = $sub->keyword1_id;
            if ($sub->keyword2_id) $keywordIds[] = $sub->keyword2_id;
            if ($sub->keyword3_id) $keywordIds[] = $sub->keyword3_id;
        }

        $keywordIds = array_unique($keywordIds);
        $keywords = TgKeywords::whereIn('id', $keywordIds)->get()->keyBy('id');

        // 组装结果
        $result = [];
        foreach ($subscriptions as $sub) {
            $keywordTexts = [];
            if ($sub->keyword1_id && isset($keywords[$sub->keyword1_id])) {
                $keywordTexts[] = $keywords[$sub->keyword1_id]->keyword_text;
            }
            if ($sub->keyword2_id && isset($keywords[$sub->keyword2_id])) {
                $keywordTexts[] = $keywords[$sub->keyword2_id]->keyword_text;
            }
            if ($sub->keyword3_id && isset($keywords[$sub->keyword3_id])) {
                $keywordTexts[] = $keywords[$sub->keyword3_id]->keyword_text;
            }

            $result[] = [
                'id' => $sub->id,
                'keywords' => $keywordTexts
            ];
        }

        return $result;
    }

    /**
     * 订阅关键词
     */
    public function subscribeKeywords(int $userId, array $keywordsArray): bool
    {
        if (empty($keywordsArray) || count($keywordsArray) > 3) {
            return false;
        }

        // 检查用户订阅数量限制
        $currentCount = TgKeywordsSub::where('user_id', $userId)
            ->where('is_active', 1)
            ->count();

        if ($currentCount >= 5) {
            throw new \Exception('每人最多订阅 5 条规则');
        }

        $keywordIds = [];
        foreach ($keywordsArray as $keyword) {
            $keywordId = $this->keywordService->addKeyword($keyword);
            if (!$keywordId) {
                return false;
            }
            $keywordIds[] = $keywordId;
        }

        return TgKeywordsSub::create([
            'user_id' => $userId,
            'keyword1_id' => $keywordIds[0] ?? 0,
            'keyword2_id' => $keywordIds[1] ?? 0,
            'keyword3_id' => $keywordIds[2] ?? 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]) ? true : false;
    }

    /**
     * 删除关键词订阅
     */
    public function deleteSubscription(int $userId, int $subscriptionId): bool
    {
        $subscription = TgKeywordsSub::where('id', $subscriptionId)
            ->where('user_id', $userId)
            ->first();

        if (!$subscription) {
            return false;
        }

        return $subscription->delete();
    }

    /**
     * 格式化订阅列表为消息文本
     */
    public function formatSubscriptionsMessage(array $subscriptions): string
    {
        if (empty($subscriptions)) {
            return "您还没有订阅任何关键词。\n\n使用 /add 来添加关键词订阅。";
        }

        $message = "📋 您的关键词订阅列表：\n\n";
        foreach ($subscriptions as $subscription) {
            $message .= "🔹 ID: {$subscription['id']}\n";
            $message .= "   关键词: " . implode(' ', $subscription['keywords']) . "\n\n";
        }

        $message .= "💡 使用 /delete <ID> 删除订阅";

        return $message;
    }
} 