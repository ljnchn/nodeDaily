<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use app\model\Post;
use support\Db;

class NodeDailyImport extends Command
{
    protected static $defaultName = 'nodeDaily:import';
    protected static $defaultDescription = '从 SQLite 全量导入到 MySQL(post)';

    /**
     * @return void
     */
    protected function configure()
    {
        // 全量导入，不需要额外参数
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name description');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('开始全量导入：SQLite(ns_posts) -> MySQL(post)');
        try {
            $result = $this->importAllFromSqlite($output);
            $output->writeln("导入完成！成功导入 {$result['imported']} 条，跳过 {$result['skipped']} 条，合计处理 {$result['total']} 条。");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('导入失败：' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * 使用 Webman 的 sqlite 连接全量分块导入
     */
    private function importAllFromSqlite(OutputInterface $output): array
    {
        $sqlite = Db::connection('sqlite');

        $total = (int) $sqlite->table('ns_posts')->count();
        $output->writeln("SQLite 总记录数：{$total}");
        if ($total === 0) {
            return ['imported' => 0, 'skipped' => 0, 'total' => 0];
        }

        $chunkSize = 500;
        $imported = 0;
        $skipped = 0;
        $processed = 0;

        $sqlite->table('ns_posts')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$imported, &$skipped, &$processed, $output) {
                foreach ($rows as $row) {
                    $processed++;
                    $id = (int) $row->id;
                    $date = (string) $row->date;
                    $title = (string) $row->title;
                    $summary = $row->summary ?? '';

                    try {
                        $exists = Post::where('id', $id)->exists();
                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        $pubDate = $this->convertDate($date);

                        $post = new Post();
                        $post->id = $id;
                        $post->title = $title;
                        $post->desc = $summary;
                        $post->category = '';
                        $post->creator = '';
                        $post->pub_date = $pubDate; // 保存为整型时间戳，和现有逻辑一致
                        $post->created_at = time();
                        $post->updated_at = time();
                        $post->from_type = 'sqlite_import';
                        $post->is_token = 1;
                        $post->is_search = 1;
                        $post->is_push = 1;
                        $post->tokens = null;

                        $post->save();
                        $imported++;
                    } catch (\Throwable $e) {
                        $output->writeln("导入 ID {$id} 失败：" . $e->getMessage());
                    }
                }
                $output->writeln("进度：已处理 {$processed} 条，已导入 {$imported} 条，已跳过 {$skipped} 条");
            }, 'id');

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * 转换日期为 Unix 时间戳
     */
    private function convertDate(string $date): int
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return time();
        }
        return $timestamp;
    }

    function import($limit)
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

   
}
