<?php

namespace app\service;

use app\model\TgPost;

class TgPostService
{
    /**
     * 解析并保存 NodeSeek 格式的数据
     * 格式: [**标题**](https://www.nodeseek.com/post-pid-1)
     */
    public function parseAndSave(string $data): array
    {
        try {
            // 解析数据格式
            $parsed = $this->parseNodeSeekFormat($data);
            
            if (!$parsed) {
                return [
                    'success' => false,
                    'message' => '无法解析数据格式'
                ];
            }

            // 检查是否已存在相同的 pid
            $existingPost = TgPost::where('pid', $parsed['pid'])->first();
            if ($existingPost) {
                return [
                    'success' => false,
                    'message' => "帖子 {$parsed['pid']} 已存在"
                ];
            }

            // 保存到数据库
            $post = TgPost::create([
                'pid' => $parsed['pid'],
                'title' => $parsed['title'],
                'desc' => '',
                'from_type' => 1 // 标记为来源类型1
            ]);

            return [
                'success' => true,
                'message' => '数据保存成功',
                'data' => [
                    'id' => $post->id,
                    'pid' => $post->pid,
                    'title' => $post->title
                ]
            ];

        } catch (\Exception $e) {
            error_log('TgPostService error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '保存失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 解析 NodeSeek 格式的数据
     * 输入: [**小额虚拟货币支付有没有啥现成的方案呀**](https://www.nodeseek.com/post-362899-1)
     * 输出: ['title' => '标题', 'pid' => 362899]
     */
    private function parseNodeSeekFormat(string $data): ?array
    {
        // 正则表达式匹配格式: [**title**](https://www.nodeseek.com/post-pid-1)
        $pattern = '/\[\*\*(.*?)\*\*\]\(https:\/\/www\.nodeseek\.com\/post-(\d+)-\d+\)/';
        
        if (preg_match($pattern, $data, $matches)) {
            return [
                'title' => trim($matches[1]),
                'pid' => (int)$matches[2]
            ];
        }

        return null;
    }

    /**
     * 批量解析并保存数据
     */
    public function batchParseAndSave(array $dataList): array
    {
        $results = [
            'success_count' => 0,
            'error_count' => 0,
            'errors' => []
        ];

        foreach ($dataList as $index => $data) {
            $result = $this->parseAndSave($data);
            
            if ($result['success']) {
                $results['success_count']++;
            } else {
                $results['error_count']++;
                $results['errors'][] = [
                    'index' => $index,
                    'data' => $data,
                    'error' => $result['message']
                ];
            }
        }

        return $results;
    }

    /**
     * 获取所有TgPost记录
     */
    public function getAllPosts(int $limit = 50): array
    {
        $posts = TgPost::orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return $posts->toArray();
    }

    /**
     * 根据 pid 获取帖子
     */
    public function getPostByPid(int $pid): ?TgPost
    {
        return TgPost::where('pid', $pid)->first();
    }
} 