<?php

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use MeiliSearch\Client;

class NodeDailySynonyms extends Command
{
    protected static $defaultName = 'nodeDaily:synonyms';
    protected static $defaultDescription = 'nodeDaily synonyms';

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
        $synonyms = $this->returnSynonyms();
        // 获取Meilisearch配置
        $config = config('meilisearch');

        // 连接到Meilisearch
        $client = new Client($config['host'], $config['key']);

        // 获取或创建索引
        $client->index('posts')->updateSynonyms($synonyms);
        return self::SUCCESS;
    }

    function returnSynonyms()
    {
        $synonyms = [
            // 常见提供商或品牌
            'bwg' => ['bandwagong', '搬瓦工'],
            'cc' => ['cloudcone', 'cloud cone'],
            'ccs' => ['ColoCrossing'],
            'rn' => ['racknerd', 'rack nerd'],
            'vultr' => ['vr'],
            'do' => ['digitalocean', 'digital ocean'],
            'linode' => [],
            'aliyun' => ['阿里云', 'ali cloud'],
            'tencentcloud' => ['腾讯云', 'tencent cloud'],
            'dmit' => ['大妈', 'dmit cloud'],

            // 地点
            'hk' => ['hongkong', '香港'],
            'jp' => ['japan', '日本', '东京', '大阪'],
            'sg' => ['singapore', '新加坡', '狮城'],
            'us' => ['usa', 'united states', '美国'],
            'la' => ['losangeles', '洛杉矶'],
            'sjc' => ['sanjose', '圣何塞'],
            'kr' => ['korea', '韩国', '首尔'],

            // 操作系统
            'centos' => ['centos linux'],
            'ubuntu' => ['ubuntu linux'],
            'debian' => ['debian linux'],
            'windows' => ['win', 'windows server'],

            // 常见操作/需求
            'dd' => ['dd系统', '重装系统'],
            '测评' => ['评测', 'review', 'benchmark', '跑分'],
            '教程' => ['指南', 'howto', 'guide'],
            '优惠' => ['促销', '打折', 'offer', 'promo'],
            '求推荐' => ['求购', '收一个'],
            '转让' => ['出售', '出'],

            // 服务器类型
            'VPS' => ['云服务器', '虚拟专用服务器', '虚拟服务器', 'Virtual Private Server'],
            '云服务器' => ['VPS', '虚拟专用服务器', 'Cloud Compute', '云主机'],
            '独立服务器' => ['物理服务器', '裸机服务器', '专用服务器', 'Dedicated Server'],
            '裸机云' => ['裸机服务器', '物理服务器', '专用服务器', 'Bare Metal Cloud'],

            // 服务提供商
            'Vultr' => ['Vultr Cloud', 'Vultr VPS'],
            'HostDare' => ['HostDare VPS', 'HostDare云服务器', 'HostDare主机'],
            'RAKsmart' => ['RAKsmart服务器', 'RAKsmart云主机', 'RAK服务器'],
            'CloudCone' => ['CC', 'CloudCone VPS', 'CloudCone服务器'],
            'DMIT' => ['DMIT VPS', 'DMIT服务器', 'DMIT主机'],
            'RackNerd' => ['RN', 'RackNerd VPS', 'RackNerd服务器'],
            'Sharktech' => ['鲨鱼服务器', 'Sharktech VPS', 'Sharktech主机'],
            'ColoCrossing' => ['CC', 'ColoCrossing VPS', '大水牛'],
            '野草云' => ['YeCaoYun', '野草云VPS', '野草云服务器'],
            '甲骨文' => ['Oracle', '甲骨文云', 'Oracle Cloud', '龟壳'],
            '谷歌云' => ['Google Cloud', '谷歌云服务', 'Google Cloud Platform'],
            '微软云' => ['Microsoft Cloud', '微软云服务', 'Microsoft Cloud Platform'],
            '阿里云' => ['阿里云服务', '阿里云平台', 'Aliyun'],
            '腾讯云' => ['腾讯云服务', '腾讯云平台', 'Tencent Cloud'],
            '华为云' => ['华为云服务', '华为云平台', 'Huawei Cloud'],
            'aws' => ['Amazon', '亚马逊'],

            // 服务器位置
            '美国服务器' => ['美国VPS', '美国云服务器', 'US Server', '美服'],
            '香港服务器' => ['香港VPS', '香港云主机', 'HK Server', '港服'],
            '日本服务器' => ['日本VPS', '日本云主机', 'Japan Server', '日服'],
            '台湾服务器' => ['台湾VPS', '台湾云主机', 'Taiwan Server', '台服'],
            '新加坡服务器' => ['新加坡VPS', '新加坡云主机', 'Singapore Server', '新服'],

            // 线路类型
            'CN2' => ['中国电信CN2', '电信CN2线路', 'CN2线路'],
            'CN2 GIA' => ['CN2 GIA线路', 'GIA线路', '电信CN2 GIA'],
            'CN2 GT' => ['CN2 GT线路', 'GT线路', '电信CN2 GT'],
            'BGP' => ['BGP线路', '多线BGP', 'BGP网络'],
            'IPLC' => ['专线', '国际专线', '国际私有租用线路', 'International Private Leased Circuit'],
            '三网优化' => ['三网直连', '三网加速', '电信联通移动优化'],

            // 浏览器相关
            '指纹浏览器' => ['反指纹浏览器', '浏览器指纹', '防关联浏览器', 'Fingerprint Browser'],
            'AdsPower' => ['AdsPower指纹浏览器', 'AdsP浏览器', 'AP浏览器'],
            'MultiLogin' => ['ML浏览器', 'MultiLogin指纹浏览器', 'ML防关联浏览器'],
            '云登指纹浏览器' => ['云登浏览器', '云登防关联浏览器'],
            'SharkLogin' => ['SharkLogin浏览器', '鲨鱼浏览器', 'SL浏览器'],
            'Ghost Browser' => ['Ghost浏览器', '幽灵浏览器'],
            'Tor Browser' => ['Tor浏览器', '洋葱浏览器', '匿名浏览器'],

            // 常用术语
            '跨境电商' => ['跨境', '出海电商', 'Cross-border E-commerce'],
            '防关联' => ['防止关联', '隐私保护', '账号安全', '防封号'],
            '独立IP' => ['专用IP', '专属IP', '独享IP', 'Dedicated IP'],
            '动态IP' => ['轮换IP', '换IP', 'Dynamic IP'],
            '住宅IP' => ['家庭IP', '居民IP', 'Residential IP'],

            // 配置相关
            '带宽' => ['网络带宽', 'Bandwidth'],
            '流量' => ['数据传输', 'Traffic', 'Data Transfer'],
            'SSD' => ['固态硬盘', 'SSD硬盘', 'Solid State Drive'],
            'KVM' => ['KVM虚拟化', 'Kernel-based Virtual Machine'],
            'CPU' => ['处理器', '中央处理器'],
            '延迟' => ['网络延迟', 'Latency', 'Ping值'],

            // 价格相关
            '优惠码' => ['折扣码', 'Coupon', 'Promo Code'],
            '特惠' => ['促销', '折扣', '优惠活动'],
            '黑五' => ['Black Friday', '黑色星期五', '黑五促销'],
        ];
        return $synonyms;
    }

}
