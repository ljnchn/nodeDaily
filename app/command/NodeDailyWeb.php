<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\TgPost;

class NodeDailyWeb extends Command
{
    protected static $defaultName = 'nodeDaily:web';
    protected static $defaultDescription = 'nodeDaily web fetch';

    /**
     * @return void
     */
    protected function configure()
    {
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = 'http://localhost:3000/scrape';
        
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

        if ($httpCode !== 200) {
            $output->writeln('HTTP Error: Status code ' . $httpCode);
            return Command::FAILURE;
        }

        // Parse JSON response
        try {
            $data = json_decode($response, true);
            if ($data === null) {
                $output->writeln('Error: Failed to parse JSON');
                return Command::FAILURE;
            }

            if (!isset($data['success']) || !$data['success']) {
                $output->writeln('Error: API response indicates failure');
                return Command::FAILURE;
            }

            if (!isset($data['data']) || !is_array($data['data'])) {
                $output->writeln('Error: No data array found in response');
                return Command::FAILURE;
            }

            $output->writeln('Successfully parsed API response');
            $output->writeln('Total items in response: ' . $data['count']);
            
            $itemCount = 0;
            $newItemCount = 0;

            // 获取最大id
            $maxId = TgPost::max('id');
            $output->writeln('Max ID: ' . $maxId);

            // Process data items
            foreach ($data['data'] as $item) {
                $itemCount++;
                
                $id = (int) $item['id'];
                
                if ($id <= $maxId) {
                    $output->writeln('Skipping item with ID: ' . $id . ' (less than or equal to max ID: ' . $maxId . ')');
                    continue;
                }
                
                if ($id === 0) {
                    $output->writeln('Skipping item with invalid ID');
                    continue;
                }

                $title = trim($item['title']);
                $author = trim($item['author']);
                $time = trim($item['time']);
                $type = trim($item['type']);
                
                // 创建描述信息
                $desc = "作者: {$author}\n时间: {$time}\n类型: {$type}";
                
                $output->writeln('Processing item:');
                $output->writeln('ID: ' . $id);
                $output->writeln('Title: ' . $title);
                $output->writeln('Author: ' . $author);
                $output->writeln('Time: ' . $time);
                $output->writeln('Type: ' . $type);
                $output->writeln('-------------------');

                // Check if post already exists
                $exists = TgPost::where('pid', $id)->exists();
                if ($exists) {
                    $output->writeln('Post already exists: ' . $id);
                    continue;
                }
                
                // Insert into TgPost model
                $post = new TgPost();
                $post->pid = $id; // 使用相同的ID作为pid
                $post->title = $title;
                $post->desc = $desc;
                $post->from_type = TgPost::FROM_TYPE_WEB; // WEB类型
                $post->handle = 0; // 默认未处理
                $post->save();
                
                $newItemCount++;
                $output->writeln('Saved new post: ' . $id);
            }
            
            $output->writeln('-------------------');
            $output->writeln('Processing complete!');
            $output->writeln('Total items processed: ' . $itemCount);
            $output->writeln('New items saved: ' . $newItemCount);
            
        } catch (\Exception $e) {
            $output->writeln('Error parsing JSON: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
} 