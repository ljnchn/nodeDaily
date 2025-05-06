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
        $output->writeln('Starting Jieba word segmentation for post titles...');
        
        ini_set('memory_limit', '1024M');
        // Initialize Jieba
        Jieba::init();
        Finalseg::init();
        Jieba::loadUserDict(base_path() . '/app/command/user_dict.txt');

        // Get posts that haven't been tokenized yet
        $posts = Post::where('is_token', 0)->limit(10)->get();
        
        foreach ($posts as $post) {
            // Perform word segmentation on the title
            $words = Jieba::cut($post->title);
            
            // Join the words with spaces and save
            // $post->title_tokens = implode(' ', $words);
            // $post->is_token = 1;
            // $post->save();
            
            $output->writeln("Processed post ID: {$post->id}, Title: {$post->title}");
            $output->writeln($words);
        }

        $output->writeln('Word segmentation completed!');
        return self::SUCCESS;
    }
}
