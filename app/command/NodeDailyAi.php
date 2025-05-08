<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class NodeDailyAi extends Command
{
    protected static $defaultName = 'nodeDaily:ai';
    protected static $defaultDescription = 'nodeDaily ai';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name description');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln('Hello nodeDaily:ai');
        $content = $this->getTitleAndAuthor(10);
        $output->writeln($content);

        $summary = $this->getSummary($content);
        $output->writeln($summary);

        return self::SUCCESS;
    }

    function getTitleAndAuthor($limit)
    {
        $content = '';
        // Get posts that haven't been tokenized yet
        $posts = Post::where('is_token', 0)->limit($limit)->orderBy('id', 'desc')->get();

        foreach ($posts as $post) {
            $title = $post->title;
            $author = $post->creator;
            $content .= $title . ' ' . $author . ' ';
        }
        return $content;
    }

    function getSummary($content)
    {
        $client = new Client();
        $apiKey = env('DEEPSEEK_API_KEY');
        
        try {
            $response = $client->post(env('DEEPSEEK_API_URL') . '/chat/completions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
                'json' => [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => '你是资深社区观察员，输出遵循 JSON 格式：\{summary: 摘要150字, keywords: 5个核心关键词数组}'],
                        ['role' => 'user', 'content' => '请基于以下帖子列表生成摘要及关键词：\n' . $content]
                    ],
                    'stream' => false,
                    'response_format' => ['type' => 'json_object']
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            return $result['choices'][0]['message']['content'];
        } catch (RequestException $e) {
            return null;
        }
    }
}
