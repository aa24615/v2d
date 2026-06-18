<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

$url = $argv[1] ?? 'https://v.kuaishou.com/Jdl248ZA';
$client = new Client([
    RequestOptions::VERIFY => false,
    RequestOptions::HTTP_ERRORS => false,
    RequestOptions::ALLOW_REDIRECTS => ['max' => 10, 'track_redirects' => true],
    RequestOptions::TIMEOUT => 15,
]);

$headers = [
    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language' => 'zh-CN,zh;q=0.9',
    'Referer' => 'https://www.kuaishou.com/',
];

$resp = $client->get($url, [RequestOptions::HEADERS => $headers]);
$body = (string) $resp->getBody();

// 找到 {"result":1,"fid" 锚点（含 atlas 的数据块）
$anchor = '{"result":1,"fid"';
$pos = strpos($body, $anchor);
if ($pos === false) {
    echo 'anchor not found' . PHP_EOL;
    exit;
}

$start = $pos;
// 从 start 开始大括号配平
$depth = 0;
$inStr = false;
$esc = false;
$end = $start;
for ($i = $start; $i < strlen($body); $i++) {
    $ch = $body[$i];
    if ($inStr) {
        if ($esc) {
            $esc = false;
        } elseif ($ch === '\\') {
            $esc = true;
        } elseif ($ch === '"') {
            $inStr = false;
        }
        continue;
    }
    if ($ch === '"') {
        $inStr = true;
    } elseif ($ch === '{') {
        $depth++;
    } elseif ($ch === '}') {
        $depth--;
        if ($depth === 0) {
            $end = $i;
            break;
        }
    }
}

$json = substr($body, $start, $end - $start + 1);
echo '=== JSON LEN: ' . strlen($json) . ' ===' . PHP_EOL;

// 解码 \u002F 等
$decoded = str_replace('\\u002F', '/', $json);
$decoded = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static function ($m) {
    return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
}, $decoded);

$data = json_decode($decoded, true);
if (!is_array($data)) {
    echo 'JSON PARSE FAILED: ' . json_last_error_msg() . PHP_EOL;
    echo substr($decoded, 0, 500) . PHP_EOL;
    exit;
}

echo '=== TOP KEYS ===' . PHP_EOL;
foreach (array_keys($data) as $k) {
    echo '- ' . $k . PHP_EOL;
}

if (isset($data['atlas'])) {
    echo '=== atlas keys ===' . PHP_EOL;
    foreach (array_keys($data['atlas']) as $k) {
        echo '- ' . $k . PHP_EOL;
    }
    echo 'cdnList: ' . json_encode($data['atlas']['cdnList'] ?? null) . PHP_EOL;
}

if (isset($data['photo']) && is_array($data['photo'])) {
    echo '=== photo keys ===' . PHP_EOL;
    foreach (array_keys($data['photo']) as $k) {
        echo '- ' . $k . PHP_EOL;
    }
    $p = $data['photo'];
    echo 'photo.caption: ' . ($p['caption'] ?? 'N/A') . PHP_EOL;
    echo 'photo.coverUrl: ' . ($p['coverUrl'] ?? 'N/A') . PHP_EOL;
    echo 'photo.photoUrl: ' . ($p['photoUrl'] ?? 'N/A') . PHP_EOL;
    echo 'photo.mainMvUrls: ' . json_encode($p['mainMvUrls'] ?? null) . PHP_EOL;
    if (isset($p['author']) || isset($p['user'])) {
        $au = $p['author'] ?? $p['user'];
        echo '=== photo author ===' . PHP_EOL;
        echo json_encode($au, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

if (isset($data['atlas'])) {
    $a = $data['atlas'];
    echo '=== atlas.list (first 2) ===' . PHP_EOL;
    echo json_encode(array_slice($a['list'] ?? [], 0, 2), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo '=== atlas.cdn (first 2) ===' . PHP_EOL;
    echo json_encode(array_slice($a['cdn'] ?? [], 0, 2), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo '=== atlas.cdnList ===' . PHP_EOL;
    echo json_encode($a['cdnList'] ?? null, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo 'list count: ' . count($a['list'] ?? []) . PHP_EOL;
}
