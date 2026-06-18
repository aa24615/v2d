<?php

/**
 * zyan/v2d SDK 使用示例。
 *
 * 运行前请先安装依赖：
 *   composer install
 *
 * 运行：
 *   php docs/test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Zyan\V2d\V2d;
use Zyan\V2d\Adapters\Douyin\DouyinAdapter;
use Zyan\V2d\Adapters\Kuaishou\KuaishouAdapter;
use Zyan\V2d\Adapters\Xiaohongshu\XiaohongshuAdapter;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\Results\VideoResult;

/**
 * 打印结果到终端。
 *
 * @param string         $title
 * @param \Zyan\V2d\Results\Result $result
 *
 * @return void
 */
function printResult(string $title, $result): void
{
    echo str_repeat('=', 60) . PHP_EOL;
    echo $title . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;
    echo '类型:     ' . $result->getType() . PHP_EOL;
    echo '平台:     ' . $result->getPlatform() . PHP_EOL;
    echo '落地链接: ' . $result->getUrl() . PHP_EOL;
    echo '标题:     ' . $result->getTitle() . PHP_EOL;
    echo '描述:     ' . $result->getDesc() . PHP_EOL;
    echo '封面:     ' . $result->getCover() . PHP_EOL;

    $author = $result->getAuthor();
    echo '作者:     ' . $author->getNickname()
        . ' (id: ' . $author->getId() . ')' . PHP_EOL;
    echo '作者头像: ' . $author->getAvatar() . PHP_EOL;

    if ($result instanceof ImageResult) {
        $images = $result->getImages();
        echo '图片数量: ' . count($images) . PHP_EOL;
        foreach ($images as $i => $url) {
            echo '  [' . ($i + 1) . '] ' . $url . PHP_EOL;
        }
    }

    if ($result instanceof VideoResult) {
        $videos = $result->getVideos();
        echo '视频数量: ' . count($videos) . PHP_EOL;
        echo '最优地址: ' . $result->getVideoUrl() . PHP_EOL;
        foreach ($videos as $i => $video) {
            echo '  [' . ($i + 1) . '] ' . ($video['url'] ?? '')
                . ' (' . ($video['quality'] ?? '') . ')' . PHP_EOL;
        }
    }

    echo PHP_EOL;
}

// ───────────────────────────────────────────────────────────────
// 示例 1：通过统一入口 V2d 自动识别平台抓取
// ───────────────────────────────────────────────────────────────
echo '【示例 1】统一入口自动识别平台' . PHP_EOL . PHP_EOL;

$v2d = new V2d();

$links = [
    'https://v.douyin.com/coTA27_qFBA/',  // 抖音
    'https://v.kuaishou.com/Jdl248ZA',     // 快手
    // 'http://xhslink.com/o/wjWTM5ZvMj',  // 小红书（需配置 Cookie，见示例 3）
];

foreach ($links as $url) {
    try {
        $result = $v2d->fetch($url);
        printResult('抓取: ' . $url, $result);
    } catch (\Throwable $e) {
        echo '抓取失败: ' . $url . PHP_EOL;
        echo '  错误: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL . PHP_EOL;
    }
}

// ───────────────────────────────────────────────────────────────
// 示例 2：单独使用某个平台适配器
// ───────────────────────────────────────────────────────────────
echo '【示例 2】单独使用适配器' . PHP_EOL . PHP_EOL;

$douyin = new DouyinAdapter();
echo 'supports 判断: ' . PHP_EOL;
echo '  抖音链接: ' . ($douyin->supports('https://v.douyin.com/xxx/') ? 'true' : 'false') . PHP_EOL;
echo '  快手链接: ' . ($douyin->supports('https://v.kuaishou.com/xxx') ? 'true' : 'false') . PHP_EOL;

try {
    $result = $douyin->fetch('https://v.douyin.com/coTA27_qFBA/');
    printResult('通过 DouyinAdapter 抓取', $result);
} catch (\Throwable $e) {
    echo '错误: ' . $e->getMessage() . PHP_EOL . PHP_EOL;
}

// ───────────────────────────────────────────────────────────────
// 示例 3：小红书需注入 Cookie 绕过风控
// ───────────────────────────────────────────────────────────────
echo '【示例 3】小红书（需配置 Cookie）' . PHP_EOL . PHP_EOL;

// 从浏览器中复制小红书的 Cookie，替换下方占位符
$cookie = 'webId=xxx; a1=xxx; web_session=xxx;';

$xhs = (new XiaohongshuAdapter())->withCookie($cookie);

try {
    $result = $xhs->fetch('http://xhslink.com/o/wjWTM5ZvMj');
    printResult('小红书抓取', $result);
} catch (\Throwable $e) {
    echo '小红书抓取失败（未配置有效 Cookie 时属正常）:' . PHP_EOL;
    echo '  ' . $e->getMessage() . PHP_EOL . PHP_EOL;
}

// ───────────────────────────────────────────────────────────────
// 示例 4：直接输出 JSON / 数组
// ───────────────────────────────────────────────────────────────
echo '【示例 4】输出 JSON 与数组' . PHP_EOL . PHP_EOL;

try {
    $json = $v2d->fetchJson('https://v.kuaishou.com/Jdl248ZA');
    echo 'JSON（前 300 字符）:' . PHP_EOL;
    echo substr($json, 0, 300) . '...' . PHP_EOL . PHP_EOL;

    $array = $v2d->fetchArray('https://v.kuaishou.com/Jdl248ZA');
    echo '数组键名: ' . implode(', ', array_keys($array)) . PHP_EOL;
    echo '图片数量: ' . count($array['images'] ?? []) . PHP_EOL;
} catch (\Throwable $e) {
    echo '错误: ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . '示例运行完毕。' . PHP_EOL;
