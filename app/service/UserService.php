<?php

namespace app\service;

use app\model\TgUsers;

class UserService
{
    private $keywordSubscriptionService;

    public function __construct()
    {
        $this->keywordSubscriptionService = new KeywordSubscriptionService();
    }

    /**
     * 注册或更新用户信息
     */
    public function registerOrUpdateUser(int $chatId, array $userData): TgUsers
    {
        $user = TgUsers::where('chat_id', $chatId)->first();

        $userInfo = [
            'username' => $userData['username'] ?? '',
            'first_name' => $userData['first_name'] ?? '',
            'last_name' => $userData['last_name'] ?? '',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($user) {
            // 检查用户之前是否被停用
            $wasInactive = !$user->is_active;
            
            // 更新现有用户，如果用户之前被停用，重新激活
            if ($wasInactive) {
                $userInfo['is_active'] = 1;
            }
            $user->update($userInfo);
            
            // 如果用户重新激活，需要处理关键词订阅数量
            if ($wasInactive) {
                $this->keywordSubscriptionService->handleUserReactivation($user->id);
            }
        } else {
            // 创建新用户，默认为活跃状态
            $userInfo['chat_id'] = $chatId;
            $userInfo['is_active'] = 1;
            $userInfo['created_at'] = date('Y-m-d H:i:s');
            $user = TgUsers::create($userInfo);
        }

        return $user;
    }

    /**
     * 通过chat_id获取用户
     */
    public function getUserByChatId(int $chatId): ?TgUsers
    {
        return TgUsers::where('chat_id', $chatId)->first();
    }

    /**
     * 通过chat_id获取用户ID
     */
    public function getUserIdByChatId(int $chatId): int
    {
        $user = $this->getUserByChatId($chatId);
        if (!$user) {
            throw new \Exception('User not found');
        }
        return $user->id;
    }

    /**
     * 停用用户（当用户停用bot时）
     */
    public function deactivateUser(int $chatId): bool
    {
        $user = $this->getUserByChatId($chatId);
        if (!$user) {
            return false;
        }

        // 先处理关键词订阅数量更新
        $this->keywordSubscriptionService->handleUserDeactivation($user->id);

        // 再更新用户状态
        $user->is_active = 0;
        $user->updated_at = date('Y-m-d H:i:s');
        return $user->save();
    }

    /**
     * 激活用户（当用户重新启用bot时）
     */
    public function activateUser(int $chatId): bool
    {
        $user = $this->getUserByChatId($chatId);
        if (!$user) {
            return false;
        }

        $user->is_active = 1;
        $user->updated_at = date('Y-m-d H:i:s');
        $result = $user->save();

        // 激活成功后处理关键词订阅数量
        if ($result) {
            $this->keywordSubscriptionService->handleUserReactivation($user->id);
        }

        return $result;
    }
} 