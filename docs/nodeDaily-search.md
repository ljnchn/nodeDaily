# NodeDaily 搜索功能

NodeDaily 搜索功能使用 Meilisearch 引擎为 NodeSeek 社区信息提供全文搜索功能。

## 功能介绍

NodeDaily 搜索功能是一个用于将 NodeSeek 帖子索引到 Meilisearch 的命令行工具。它能够从数据库中读取未索引的帖子（is_search=0），并将其添加到 Meilisearch 搜索引擎中，方便用户快速搜索相关内容。

### 主要特点

- 自动读取未索引帖子并添加到 Meilisearch
- 支持批量处理或处理所有未索引数据
- 实时显示处理进度和耗时统计
- 自动更新帖子索引状态
- 支持配置搜索字段、过滤和排序

## 环境要求

- PHP 8.1+
- Meilisearch 服务器（本地或远程）
- Composer

## 安装配置

1. 安装 Meilisearch（如果尚未安装）

```bash
# 使用 Docker 安装 Meilisearch
docker run -p 7700:7700 -v $(pwd)/meili_data:/meili_data getmeili/meilisearch:latest
```

2. 配置 Meilisearch

在项目根目录中添加环境变量（`.env` 文件）或直接在 `config/meilisearch.php` 文件中设置：

```php
// config/meilisearch.php
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
                'from_type',
                'pub_date',
                'created_at'
            ],
            'sortableAttributes' => [
                'pub_date',
                'created_at',
                'updated_at'
            ]
        ]
    ]
];
```

## 使用方法

### 命令参数

```bash
php webman NodeDaily:search [选项]
```

#### 可用选项

- `--limit=<数量>` 或 `-l <数量>`: 指定每次处理的帖子数量，默认为100条
- `--all` 或 `-a`: 处理所有未索引的帖子数据（忽略limit参数）

### 使用示例

1. 使用默认参数处理100条未索引数据

```bash
php webman NodeDaily:search
```

2. 处理指定数量的未索引数据

```bash
php webman NodeDaily:search --limit=200
```

3. 处理所有未索引数据

```bash
php webman NodeDaily:search --all
```

## 处理逻辑

1. 工具会查询所有 `is_search=0` 的帖子记录
2. 将帖子数据添加到 Meilisearch 索引中
3. 将 `is_search` 字段设置为 1，表示已处理
4. 每批次处理会显示进度

## 数据结构

以下是添加到 Meilisearch 的数据结构：

```json
{
  "id": 123,
  "title": "帖子标题",
  "desc": "帖子描述",
  "category": "分类",
  "creator": "发布者",
  "pub_date": "2023-05-20 12:00:00",
  "created_at": "2023-05-20 12:00:00",
  "updated_at": "2023-05-20 12:00:00",
  "from_type": "来源类型",
  "tokens": ["关键词1", "关键词2", "关键词3"]
}
```

## 注意事项

- 确保 Meilisearch 服务正常运行
- 首次索引大量数据可能需要较长时间
- 推荐先使用 `NodeDaily:jieba` 命令进行分词，然后再使用搜索索引功能
- 确保数据库中 post 表有 `is_search` 字段用于标记索引状态 