<?php

namespace app\controller;

use support\Request;
use MeiliSearch\Client;
use MeiliSearch\Contracts\SearchQuery;
use MeiliSearch\Contracts\MultiSearchFederation;

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

    public function index(Request $request)
    {
        // 获取所有分类
        $categories = $this->getCategories();

        return view('search/index', [
            'categories' => $categories
        ]);
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
            'attributesToHighlight' => ['title', 'desc']
        ];

        // 添加分类过滤
        if (!empty($category)) {
            $searchParams['filter'] = "category = '{$category}'";
        }
        $results = null;
        // 如果$query包含空格，则添加或条件
        if (strpos($query, '||') !== false) {
            $queryArr = explode('||', $query);
            if (count($queryArr) == 2) {
                // 使用 multi-search
                $results = $this->client->multiSearch(
                    [
                        (new SearchQuery())
                            ->setIndexUid('posts')
                            ->setQuery($queryArr[0]),
                        (new SearchQuery())
                            ->setIndexUid('posts')
                            ->setQuery($queryArr[1]),
                    ],
                    (new MultiSearchFederation())
                );
            }
        }

        if ($results == null) {
            $results = $this->index->search($query, $searchParams);
        }

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

    private function getCategories()
    {
        return ['trade', 'daily', 'review', 'info', 'carpool', 'promotion', 'tech', 'expose', 'sha', 'dev'];
    }
}
