<?php
/**
 * 探测抖音/快手作品中的音乐相关字段结构。
 */
require __DIR__ . '/../vendor/autoload.php';

use Zyan\V2d\Adapters\Douyin\DouyinAdapter;
use Zyan\V2d\Adapters\Kuaishou\KuaishouAdapter;

function pickKeys(array $arr, array $candidates): array {
    $out = [];
    foreach ($candidates as $k) {
        if (array_key_exists($k, $arr)) {
            $v = $arr[$k];
            $out[$k] = is_array($v) ? 'array(' . count($v) . ')' : gettype($v) . ':' . substr((string)$v, 0, 40);
        }
    }
    return $out;
}

echo "===== DOUYIN =====\n";
try {
    $r = (new DouyinAdapter())->fetch('https://v.douyin.com/ws3MyrdUru0/');
    $raw = $r->getRaw();
    echo "top keys: " . implode(',', array_keys($raw)) . "\n";
    echo "music-related top keys: " . json_encode(pickKeys($raw, ['music','sound','bgm','audio']), JSON_UNESCAPED_UNICODE) . "\n";
    if (isset($raw['music']) && is_array($raw['music'])) {
        echo "music sub-keys: " . implode(',', array_keys($raw['music'])) . "\n";
        echo "music sample: " . json_encode($raw['music'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
} catch (\Throwable $e) { echo "ERR " . $e->getMessage() . "\n"; }

echo "\n===== KUAISHOU =====\n";
try {
    $r = (new KuaishouAdapter())->fetch('https://v.kuaishou.com/nHpAGzrX');
    $raw = $r->getRaw();
    echo "top keys: " . implode(',', array_keys($raw)) . "\n";
    echo "music-related top keys: " . json_encode(pickKeys($raw, ['soundtrack','sound','audio','bgm','webpmidInfo','photoType']), JSON_UNESCAPED_UNICODE) . "\n";
    foreach (['soundtrack','sound','audio'] as $k) {
        if (isset($raw[$k]) && is_array($raw[$k])) {
            echo "$k sub-keys: " . implode(',', array_keys($raw[$k])) . "\n";
            echo "$k sample: " . json_encode($raw[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
    }
} catch (\Throwable $e) { echo "ERR " . $e->getMessage() . "\n"; }
