<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;

class NodeDailyKeyWords extends Command
{
    protected static $defaultName = 'NodeDaily:keyWords';
    protected static $defaultDescription = 'NodeDaily keyWords';

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
        $output->writeln('Hello NodeDaily:keyWords');
        $keyWords = $this->getKeyWords();
        var_dump($keyWords);
        return self::SUCCESS;
    }

    private function getKeyWords($limit = 100)
    {
        $postModels = Post::where('is_token', 1)->limit($limit)->orderBy('id', 'desc')->get();
        $keyWords = [];
        // 获取tokens，统计每个token出现的次数
        foreach ($postModels as $postModel) {
            $tokens = json_decode($postModel->tokens, true) ?? [];
            foreach ($tokens as $token) {
                $keyWords[$token] = isset($keyWords[$token]) ? $keyWords[$token] + 1 : 1;
            }
        }
        // 按出现次数排序
        return $keyWords;
    }

}
