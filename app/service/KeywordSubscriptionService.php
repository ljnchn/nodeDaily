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
        $index = 1;
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
                'index' => $index,
                'id' => $sub->id, // 保留真实ID用于删除操作
                'keywords' => $keywordTexts
            ];
            $index++;
        }

        return $result;
    }

    /**
     * 订阅关键词
     */
    public function subscribeKeywords(int $userId, array $keywordsArray, string $keywordsText): bool
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

        $created = TgKeywordsSub::create([
            'user_id' => $userId,
            'keywords_text' => $keywordsText,
            'keywords_count' => count($keywordsArray),
            'keyword1_id' => $keywordIds[0] ?? 0,
            'keyword2_id' => $keywordIds[1] ?? 0,
            'keyword3_id' => $keywordIds[2] ?? 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($created) {
            // 订阅成功后，增加关键词的订阅数量
            $this->keywordService->incrementSubNumBatch($keywordIds);
            return true;
        }

        return false;
    }

    /**
     * 删除关键词订阅（根据用户显示的序号）
     */
    public function deleteSubscription(int $userId, int $index): bool
    {
        // 获取用户的所有订阅
        $subscriptions = TgKeywordsSub::where('user_id', $userId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        // 检查序号是否有效
        if ($index < 1 || $index > $subscriptions->count()) {
            return false;
        }

        // 获取对应序号的订阅（序号从1开始，数组索引从0开始）
        $subscriptionArray = $subscriptions->toArray();
        $targetSubscription = $subscriptionArray[$index - 1];
        
        // 获取要删除的关键词ID列表
        $keywordIds = array_filter([
            $targetSubscription['keyword1_id'],
            $targetSubscription['keyword2_id'],
            $targetSubscription['keyword3_id']
        ]);
        
        // 根据ID删除对应的记录
        $result = TgKeywordsSub::where('id', $targetSubscription['id'])
            ->where('user_id', $userId)
            ->delete();
            
        if ($result > 0 && !empty($keywordIds)) {
            // 删除成功后，减少关键词的订阅数量
            $this->keywordService->decrementSubNumBatch($keywordIds);
        }
        
        return $result > 0;
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
            $message .= "🔹 序号: {$subscription['index']}\n";
            $message .= "   关键词: " . implode(' ', $subscription['keywords']) . "\n\n";
        }

        $message .= "💡 使用 `/del ` 序号 删除订阅";

        return $message;
    }

    /**
     * 处理用户停用时的关键词订阅数量更新
     * 当用户屏蔽bot时调用此方法
     */
    public function handleUserDeactivation(int $userId): bool
    {
        // 获取用户的所有活跃订阅
        $subscriptions = TgKeywordsSub::where('user_id', $userId)
            ->where('is_active', 1)
            ->get();

        if ($subscriptions->isEmpty()) {
            return true;
        }

        // 收集所有关键词ID
        $keywordIds = [];
        foreach ($subscriptions as $subscription) {
            if ($subscription->keyword1_id) $keywordIds[] = $subscription->keyword1_id;
            if ($subscription->keyword2_id) $keywordIds[] = $subscription->keyword2_id;
            if ($subscription->keyword3_id) $keywordIds[] = $subscription->keyword3_id;
        }

        // 去重
        $keywordIds = array_unique($keywordIds);

        // 停用用户的所有订阅
        TgKeywordsSub::where('user_id', $userId)
            ->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        // 减少关键词的订阅数量
        if (!empty($keywordIds)) {
            $this->keywordService->decrementSubNumBatch($keywordIds);
        }

        return true;
    }

    /**
     * 处理用户重新激活时的关键词订阅数量更新
     * 当用户重新启用bot时调用此方法
     */
    public function handleUserReactivation(int $userId): bool
    {
        // 获取用户的所有非活跃订阅
        $subscriptions = TgKeywordsSub::where('user_id', $userId)
            ->where('is_active', 0)
            ->get();

        if ($subscriptions->isEmpty()) {
            return true;
        }

        // 收集所有关键词ID
        $keywordIds = [];
        foreach ($subscriptions as $subscription) {
            if ($subscription->keyword1_id) $keywordIds[] = $subscription->keyword1_id;
            if ($subscription->keyword2_id) $keywordIds[] = $subscription->keyword2_id;
            if ($subscription->keyword3_id) $keywordIds[] = $subscription->keyword3_id;
        }

        // 去重
        $keywordIds = array_unique($keywordIds);

        // 重新激活用户的所有订阅
        TgKeywordsSub::where('user_id', $userId)
            ->where('is_active', 0)
            ->update(['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        // 增加关键词的订阅数量
        if (!empty($keywordIds)) {
            $this->keywordService->incrementSubNumBatch($keywordIds);
        }

        return true;
    }
}
