<?php
return [
    'host' => getenv('MEILISEARCH_HOST') ?: 'http://127.0.0.1:7700',
    'key' => getenv('MEILISEARCH_KEY') ?: '',
    'indexes' => [
        'posts' => [
            'primaryKey' => 'id',
            'searchableAttributes' => [
                'title',
                'desc',
                'category',
                'creator',
                'tokens'
            ],
            'filterableAttributes' => [
                'category',
                'creator',
                'pub_date',
            ],
            'sortableAttributes' => [
                'pub_date',
            ]
        ]
    ]
]; 