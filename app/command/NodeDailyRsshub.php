<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;

class NodeDailyRsshub extends Command
{
    protected static $defaultName = 'nodeDaily:rsshub';
    protected static $defaultDescription = 'nodeDaily rsshub';

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
        $url = 'https://rss.owo.nz/telegram/channel/nodeseekc';

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

        // Parse XML response
        try {
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                $output->writeln('Error: Failed to parse XML');
                return Command::FAILURE;
            }

            $output->writeln('Successfully parsed RSS feed');
            $itemCount = 0;
            $newItemCount = 0;

            // 获取最大id
            $maxId = Post::max('id');
            $output->writeln('Max ID: ' . $maxId);

            // Process RSS items
            foreach ($xml->channel->item as $item) {
                $itemCount++;
                
                // Extract ID from description (e.g., https://www.nodeseek.com/post-359520-1 -> 359520)
                $desc = trim((string) $item->description);
                preg_match('/post-(\d+)-/', $desc, $matches);
                $id = isset($matches[1]) ? (int) $matches[1] : 0;
                
                if ($id <= $maxId) {
                    $output->writeln('Skipping item with ID: ' . $id . ' (less than or equal to max ID: ' . $maxId . ')');
                    continue;
                }
                
                if ($id === 0) {
                    $output->writeln('Skipping item with invalid ID from description');
                    continue;
                }

                $title = trim((string) $item->title);
                $category = 'daily';
                $pubDate = trim((string) $item->pubDate);
                $pub_date = strtotime($pubDate);
                
                // Remove image tags from description (already obtained above)
                $cleanDesc = $this->removeImageTags($desc);
                
                // Extract creator from description or use default
                $creator = 'nodeseekc';
                
                $output->writeln('Processing item:');
                $output->writeln('ID: ' . $id);
                $output->writeln('Title: ' . $title);
                $output->writeln('Category: ' . $category);
                $output->writeln('PubDate: ' . $pubDate . ' (' . $pub_date . ')');
                $output->writeln('Creator: ' . $creator);
                $output->writeln('-------------------');

                // Check if post already exists
                $exists = Post::where('id', $id)->exists();
                if ($exists) {
                    $output->writeln('Post already exists: ' . $id);
                    continue;
                }
                
                // Insert into post model
                $post = new Post();
                $post->id = $id;
                $post->creator = $creator;
                $post->title = $title;
                $post->category = $category;
                $post->desc = '';
                $post->pub_date = $pub_date;
                $post->created_at = time();
                $post->updated_at = time();
                $post->from_type = 'rsshub';
                $post->save();
                
                $newItemCount++;
                $output->writeln('Saved new post: ' . $id);
            }
            
            $output->writeln('-------------------');
            $output->writeln('Processing complete!');
            $output->writeln('Total items processed: ' . $itemCount);
            $output->writeln('New items saved: ' . $newItemCount);
            
        } catch (\Exception $e) {
            $output->writeln('Error parsing XML: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Remove image tags from description
     * @param string $content
     * @return string
     */
    private function removeImageTags($content)
    {
        // Remove <img> tags
        $content = preg_replace('/<img[^>]*>/i', '', $content);
        
        // Remove image URLs that might be in plain text
        $content = preg_replace('/https?:\/\/[^\s]*\.(jpg|jpeg|png|gif|webp|svg)(\?[^\s]*)?/i', '', $content);
        
        // Remove telegram CDN image URLs
        $content = preg_replace('/https?:\/\/cdn\d*\.cdn-telegram\.org\/[^\s]*/i', '', $content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }
}
