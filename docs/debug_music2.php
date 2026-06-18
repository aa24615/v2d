<?php
require __DIR__ . '/../vendor/autoload.php';
use Zyan\V2d\Adapters\Kuaishou\KuaishouAdapter;
use Zyan\V2d\Adapters\Douyin\DouyinAdapter;

echo "===== KUAISHOU soundTrack =====\n";
$r = (new KuaishouAdapter())->fetch('https://v.kuaishou.com/nHpAGzrX');
$raw = $r->getRaw();
foreach (['soundTrack','manifest','audio'] as $k) {
    if (isset($raw[$k])) {
        $v = $raw[$k];
        echo "$k: " . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : gettype($v).':'.$v) . "\n";
    }
}

echo "\n===== DOUYIN music full =====\n";
$r2 = (new DouyinAdapter())->fetch('https://v.douyin.com/ws3MyrdUru0/');
$raw2 = $r2->getRaw();
if (isset($raw2['music'])) {
    // 检查所有含 url 的字段
    $m = $raw2['music'];
    foreach ($m as $k => $v) {
        if (is_array($v)) {
            // 看是否含 url_list
            if (isset($v['url_list'])) {
                echo "music.$k has url_list[0]: " . ($v['url_list'][0] ?? 'EMPTY') . "\n";
            } else {
                echo "music.$k: array(" . count($v) . ")\n";
            }
        } else {
            echo "music.$k = $v\n";
        }
    }
}
