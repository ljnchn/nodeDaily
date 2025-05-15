<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;
use Fukuball\Jieba\Jieba;
use Fukuball\Jieba\Finalseg;

class NodeDailyJieba extends Command
{
    protected static $defaultName = 'nodeDaily:jieba';
    protected static $defaultDescription = 'nodeDaily jieba';
    
    /**
     * 停用词列表
     * @var array
     */
    protected $stopWords = [];

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name description');
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '每批处理数量', 100);
        $this->addOption('all', 'a', InputOption::VALUE_NONE, '处理所有未分词数据');
    }

    /**
     * 加载停用词列表
     */
    protected function loadStopWords()
    {
        $stopWordsFile = base_path() . '/app/command/stop_words.txt';
        if (file_exists($stopWordsFile)) {
            $content = file_get_contents($stopWordsFile);
            $words = explode("\n", $content);
            foreach ($words as $word) {
                $word = trim($word);
                if (!empty($word)) {
                    $this->stopWords[$word] = true;
                }
            }
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $limit = (int)$input->getOption('limit');
        $processAll = $input->getOption('all');
        
        $output->writeln('Starting Jieba word segmentation for post titles...');
        
        ini_set('memory_limit', '1024M');
        // Initialize Jieba
        Jieba::init();
        Finalseg::init();
        Jieba::loadUserDict(base_path() . '/app/command/user_dict.txt');
        
        // 加载停用词
        $this->loadStopWords();
        $output->writeln('Loaded ' . count($this->stopWords) . ' stop words.');

        // 获取需要处理的未分词文章
        $query = Post::where('is_token', 0);
        
        if (!$processAll) {
            $query = $query->limit($limit);
        }
        
        $posts = $query->get();
        $totalPosts = count($posts);
        
        $output->writeln("Found {$totalPosts} posts to process" . ($processAll ? ' (processing all)' : ''));
        
        $count = 0;
        $startTime = microtime(true);
        
        foreach ($posts as $post) {
            try {
                // Perform word segmentation on the title
                $words = Jieba::cut($post->title);
                
                // 过滤掉单字符、停用词和不常用词
                $filteredWords = [];
                foreach ($words as $word) {
                    $word = trim($word);
                    // 跳过空字符串和单字符词
                    if (empty($word) || mb_strlen($word, 'UTF-8') < 2) {
                        continue;
                    }
                    
                    // 跳过停用词
                    if (isset($this->stopWords[$word])) {
                        continue;
                    }
                    
                    $filteredWords[] = $word;
                }
                
                // Join the words with spaces and update
                $tokens = json_encode($filteredWords);
                Post::where('id', $post->id)->update([
                    'tokens' => $tokens,
                    'is_token' => 1
                ]);
                
                $count++;
                
                // 每处理100条显示一次进度
                if ($count % 100 === 0 || $count === $totalPosts) {
                    $output->writeln("Progress: {$count}/{$totalPosts} posts processed (" . round(($count / $totalPosts) * 100, 2) . "%)");
                }
            } catch (\Exception $e) {
                $output->writeln("Error processing post ID: {$post->id}: " . $e->getMessage());
            }
        }

        $endTime = microtime(true);
        $timeUsed = round($endTime - $startTime, 2);
        
        $output->writeln("Word segmentation completed! Processed {$count} posts in {$timeUsed} seconds.");
        return self::SUCCESS;
    }
}
