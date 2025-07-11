<?php

namespace app\controller;

use support\Request;
use MeiliSearch\Client;

class SearchController
{
    protected $client;
    protected $index;

    public function __construct()
    {
        $config = config('meilisearch');
        $this->client = new Client($config['host'], $config['key']);
        $this->index = $this->client->index('posts');
    }

    public function categoryList(Request $request)
    {
        // 获取所有分类
        $categories = [
            ['key' => 'daily', 'value' => '每日'],
            ['key' => 'trade', 'value' => '交易'],
            ['key' => 'review', 'value' => '评测'],
            ['key' => 'info', 'value' => '资讯'],
            ['key' => 'carpool', 'value' => '拼车'],
            ['key' => 'promotion', 'value' => '推广'],
            ['key' => 'tech', 'value' => '技术'],
        ];

        return json([
            'data' => $categories,
            'code' => 0,
            'msg' => 'success'
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $category = $request->input('category', '');
        $page = (int)$request->input('page', 1);
        $perPage = 10;

        $searchParams = [
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'sort' => ['pub_date:desc'],
            'attributesToHighlight' => ['title', 'desc'],
            // 'showRankingScore' => false // 不显示相关度分数
        ];

        // 添加分类过滤
        if (!empty($category)) {
            $searchParams['filter'] = "category = '{$category}'";
        }

        // 如果有搜索关键词，仍然强制按时间排序
        if (!empty($query)) {
            // 确保排序优先级高于相关度
            $searchParams['rankingScoreThreshold'] = null;
        }

        $results = $this->index->search($query, $searchParams);

        $hits = $results->getHits();
        foreach ($hits as &$hit) {
            $hit['pub_date'] = date('Y-m-d H:i:s', $hit['pub_date']);
        }

        return json([
            'hits' => $hits,
            'estimatedTotalHits' => $results->getEstimatedTotalHits(),
            'processingTimeMs' => $results->getProcessingTimeMs(),
            'query' => $query,
            'category' => $category,
            'page' => $page,
            'totalPages' => ceil($results->getEstimatedTotalHits() / $perPage)
        ]);
    }
}
