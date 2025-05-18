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

    public function index(Request $request)
    {
        // 获取所有分类
        $categories = $this->getCategories();
        
        return view('search/index', [
            'categories' => $categories
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

    private function getCategories()
    {        
        return ['trade', 'daily', 'review', 'info', 'carpool', 'promotion', 'tech', 'expose', 'sha', 'dev'];
    }
} 