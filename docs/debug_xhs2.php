<?php
/**
 * 提取并分析小红书 __INITIAL_STATE__ 结构。
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

$url = 'http://xhslink.com/o/6cQi47pyi36';

$client = new Client([
    RequestOptions::VERIFY => false,
    RequestOptions::HTTP_ERRORS => false,
    RequestOptions::ALLOW_REDIRECTS => ['max' => 10, 'track_redirects' => true],
]);

$headers = [
    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language' => 'zh-CN,zh;q=0.9',
    'Referer' => 'https://www.xiaohongshu.com/',
];

$resp = $client->request('GET', $url, [RequestOptions::HEADERS => $headers, RequestOptions::TIMEOUT => 15]);
$body = (string) $resp->getBody();

// 找到 __INITIAL_STATE__ 起点
$pos = strpos($body, '__INITIAL_STATE__');
if ($pos === false) {
    echo "no __INITIAL_STATE__\n";
    exit;
}

// 找到其后的 =
$eq = strpos($body, '=', $pos);
$jsonStart = $eq + 1;
// 跳过空白
while ($jsonStart < strlen($body) && ctype_space($body[$jsonStart])) {
    $jsonStart++;
}

echo 'JSON starts at: ' . $jsonStart . ' first chars: ' . substr($body, $jsonStart, 60) . PHP_EOL;

// 大括号配平提取
$depth = 0; $inStr = false; $esc = false; $len = strlen($body);
$end = $jsonStart;
for ($i = $jsonStart; $i < $len; $i++) {
    $ch = $body[$i];
    if ($inStr) {
        if ($esc) { $esc = false; }
        elseif ($ch === '\\') { $esc = true; }
        elseif ($ch === '"') { $inStr = false; }
        continue;
    }
    if ($ch === '"') { $inStr = true; }
    elseif ($ch === '{') { $depth++; }
    elseif ($ch === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
}

$json = substr($body, $jsonStart, $end - $jsonStart + 1);
$json = str_replace('\\/', '/', $json);
$data = json_decode($json, true);

if (!is_array($data)) {
    echo "JSON decode failed: " . json_last_error_msg() . PHP_EOL;
    echo "tail: " . substr($json, -200) . PHP_EOL;
    exit;
}

echo 'TOP LEVEL KEYS: ' . implode(', ', array_keys($data)) . PHP_EOL;

function dumpKeys($arr, $prefix = '', $depth = 0) {
    if ($depth > 3 || !is_array($arr)) return;
    foreach ($arr as $k => $v) {
        $type = is_array($v) ? 'array(' . count($v) . ')' : gettype($v);
        echo str_repeat('  ', $depth) . $prefix . $k . ' : ' . $type . PHP_EOL;
        if (is_array($v) && $depth < 2) {
            // only show keys for small arrays or first few
            $i = 0;
            foreach ($v as $kk => $vv) {
                if ($i++ > 3) { echo str_repeat('  ', $depth+1) . "...\n"; break; }
                $t2 = is_array($vv) ? 'array('.count($vv).')' : gettype($vv);
                echo str_repeat('  ', $depth+1) . $kk . ' : ' . $t2 . PHP_EOL;
            }
        }
    }
}

echo PHP_EOL . '===== STRUCTURE (depth 2) =====' . PHP_EOL;
dumpKeys($data, '', 0);
