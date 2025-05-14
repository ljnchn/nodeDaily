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
        $content = $this->getTitleAndAuthor(50);
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
            $content .= $title . '-' . $author . "\n";
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
                    'model' => 'deepseek-ai/DeepSeek-V3',
                    'messages' => [
                        ['role' => 'system', 'content' => '角色: NodeSeek 资深社区观察员，兼技术段子手。
任务: 基于提供的 NodeSeek 帖子信息（标题、作者），用专业且风趣的口吻总结社区近期动态。
总结要点:
开场: 一句幽默的开场白概括本期看点。
热议话题: 生动描述社区热议的几个核心主题（技术、服务、事件等），点出讨论焦点和主要观点，可适当使用社区黑话/梗。
亮点/槽点: 提及有趣的讨论、神回复或值得注意的“坑”。
收尾: 一句风趣且有洞察的总结或给社区的“温馨提示”。
风格要求: 语言生动，吐槽精准，避免死板。让总结读起来像一个懂行的老鸟在分享观察。报告标题请有创意, 字数限制在200个字以内。'],
                        ['role' => 'user', 'content' => '帖子列表:\n' . $content]
                    ],
                    'stream' => false,
                    'max_tokens' => 131072,
                    'temperature' => 1,
                    // 'response_format' => ['type' => 'json_object']
                ]
            ]);
            $result = json_decode($response->getBody()->getContents(), true);

            return $result['choices'][0]['message']['content'];
        } catch (RequestException $e) {
            return null;
        }
    }
}
