<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;
use Meilisearch\Client;

class NodeDailySearch extends Command
{
    protected static $defaultName = 'NodeDaily:search';
    protected static $defaultDescription = 'NodeDaily search and index posts to Meilisearch';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '每批处理数量', 100);
        $this->addOption('all', 'a', InputOption::VALUE_NONE, '处理所有未索引数据');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int)$input->getOption('limit');
        $processAll = $input->getOption('all');
        
        $output->writeln('开始将未索引的帖子添加到Meilisearch...');
        
        // 获取Meilisearch配置
        $config = config('meilisearch');
        
        // 连接到Meilisearch
        $client = new Client($config['host'], $config['key']);
        
        // 获取或创建索引
        $index = $client->index('posts');
        
        // 配置索引设置
        if (isset($config['indexes']['posts'])) {
            $indexConfig = $config['indexes']['posts'];
            
            // 设置可搜索字段
            if (isset($indexConfig['searchableAttributes'])) {
                $index->updateSettings([
                    'searchableAttributes' => $indexConfig['searchableAttributes']
                ]);
            }
            
            // 设置可过滤字段
            if (isset($indexConfig['filterableAttributes'])) {
                $index->updateSettings([
                    'filterableAttributes' => $indexConfig['filterableAttributes']
                ]);
            }
            
            // 设置可排序字段
            if (isset($indexConfig['sortableAttributes'])) {
                $index->updateSettings([
                    'sortableAttributes' => $indexConfig['sortableAttributes']
                ]);
            }
        }
        
        // 获取需要处理的未索引文章
        $query = Post::where('is_search', 0);
        
        if (!$processAll) {
            $query = $query->limit($limit);
        }
        
        $posts = $query->get();
        $totalPosts = count($posts);
        
        $output->writeln("找到 {$totalPosts} 篇未索引的帖子" . ($processAll ? ' (处理全部)' : " (处理 {$limit} 篇)"));
        
        if ($totalPosts === 0) {
            $output->writeln("没有找到需要索引的帖子，任务完成！");
            return self::SUCCESS;
        }
        
        $count = 0;
        $startTime = microtime(true);
        $documents = [];
        
        foreach ($posts as $post) {
            try {
                // 准备文档数据
                $documents[] = [
                    'id' => $post->id,
                    'title' => $post->title,
                    'desc' => $post->desc,
                    'category' => $post->category,
                    'creator' => $post->creator,
                    'pub_date' => $post->pub_date,
                    'tokens' => []
                ];
                
                $count++;
                
                // 每100条或最后一批添加到索引
                if (count($documents) >= 100 || $count === $totalPosts) {
                    $index->addDocuments($documents);
                    $output->writeln("进度: {$count}/{$totalPosts} 篇帖子已索引 (" . round(($count / $totalPosts) * 100, 2) . "%)");
                    $documents = [];
                }
            } catch (\Exception $e) {
                $output->writeln("处理帖子 ID: {$post->id} 时出错: " . $e->getMessage());
            }
        }

        // 更新已处理的帖子状态
        if ($count > 0) {
            $postIds = $posts->pluck('id')->toArray();
            Post::whereIn('id', $postIds)->update(['is_search' => 1]);
            $output->writeln("已更新 {$count} 篇帖子的索引状态。");
        }
        
        $endTime = microtime(true);
        $timeUsed = round($endTime - $startTime, 2);
        
        $output->writeln("索引完成！共处理 {$count} 篇帖子，耗时 {$timeUsed} 秒。");
        return self::SUCCESS;
    }
}
