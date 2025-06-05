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
            // 更新现有用户
            $user->update($userInfo);
        } else {
            // 创建新用户
            $userInfo['chat_id'] = $chatId;
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
} 