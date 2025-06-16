<?php

namespace app\service;

use app\model\TgUsers;

class UserService
{
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
            // 更新现有用户，如果用户之前被停用，重新激活
            if (!$user->is_active) {
                $userInfo['is_active'] = 1;
            }
            $user->update($userInfo);
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
        return $user->save();
    }
} 