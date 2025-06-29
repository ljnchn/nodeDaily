<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;

class NodeDailyRss extends Command
{
    protected static $defaultName = 'nodeDaily:rss';
    protected static $defaultDescription = 'nodeDaily rss';

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
        $url = 'https://nodeseek-rss-proxy.ljn.workers.dev/';

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        // Execute the request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            $output->writeln('Error: ' . curl_error($ch));
            curl_close($ch);
            return Command::FAILURE;
        }

        // Get HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close cURL session
        curl_close($ch);

        // Output the response
        $output->writeln('HTTP Status Code: ' . $httpCode);
        $output->writeln('Response: ' . $response);

        // Parse XML response
        try {
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                $output->writeln('Error: Failed to parse XML');
                return Command::FAILURE;
            }

            // Process RSS items
            foreach ($xml->channel->item as $item) {
                $id = (int) $item->guid;
                $title = trim((string) $item->title);
                $category = trim((string) $item->category);
                $desc = trim((string) $item->description);
                $pub_date = strtotime((string) $item->pubDate);
                // 使用命名空间访问 dc:creator
                $dc = $item->children('http://purl.org/dc/elements/1.1/');
                $creator = trim((string) $dc->creator);
                $output->writeln('ID: ' . $id);
                $output->writeln('Title: ' . $title);
                $output->writeln('Category: ' . $category);
                $output->writeln('Description: ' . $desc);
                $output->writeln('PubDate: ' . $pub_date);
                $output->writeln('Creator: ' . $creator);
                $output->writeln('-------------------');

                $exists = Post::where('id', $id)->exists();
                if ($exists) {
                    $output->writeln('Post already exists: ' . $id);
                    continue;
                }
                // 插入到model post
                $post = new Post();
                $post->id = $id;
                $post->creator = $creator;
                $post->title = $title;
                $post->category = $category;
                $post->desc = $desc;
                $post->pub_date = $pub_date;
                $post->created_at = time();
                $post->updated_at = time();
                $post->from_type = 'rss';
                $post->save();
            }
        } catch (\Exception $e) {
            $output->writeln('Error parsing XML: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

}
