<?php
/**
 * 验证视频抓取是否对真实链接生效。
 *
 * 运行：php docs/verify_video.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Zyan\V2d\V2d;
use Zyan\V2d\Results\VideoResult;
use Zyan\V2d\Results\ImageResult;

$links = [
    'douyin'      => 'https://v.douyin.com/ws3MyrdUru0/',
    'kuaishou'    => 'https://v.kuaishou.com/nHpAGzrX',
    'xiaohongshu' => 'http://xhslink.com/o/6cQi47pyi36',
];

$v2d = new V2d();

foreach ($links as $platform => $url) {
    echo str_repeat('=', 60) . PHP_EOL;
    echo sprintf('[%s] %s', $platform, $url) . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;

    try {
        $result = $v2d->fetch($url);

        echo '类型:   ' . $result->getType() . PHP_EOL;
        echo '平台:   ' . $result->getPlatform() . PHP_EOL;
        echo '标题:   ' . $result->getTitle() . PHP_EOL;
        echo '作者:   ' . $result->getAuthor()->getNickname() . PHP_EOL;
        echo '封面:   ' . $result->getCover() . PHP_EOL;

        if ($result instanceof VideoResult) {
            echo '视频数量: ' . count($result->getVideos()) . PHP_EOL;
            echo '最优地址: ' . $result->getVideoUrl() . PHP_EOL;
            foreach ($result->getVideos() as $i => $v) {
                echo sprintf('  [%d] %s (%s)', $i + 1, $v['url'] ?? '', $v['quality'] ?? '') . PHP_EOL;
            }
        } elseif ($result instanceof ImageResult) {
            echo '图片数量: ' . count($result->getImages()) . PHP_EOL;
        }
    } catch (\Throwable $e) {
        echo '失败: ' . get_class($e) . PHP_EOL;
        echo '  ' . $e->getMessage() . PHP_EOL;
    }

    echo PHP_EOL;
}
