<?php
/**
 * 定位小红书 __INITIAL_STATE__ JSON 解析失败的确切位置。
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
    'Accept-Language' => 'zh-CN,zh;q=0..9',
    'Referer' => 'https://www.xiaohongshu.com/',
];

$resp = $client->request('GET', $url, [RequestOptions::HEADERS => $headers, RequestOptions::TIMEOUT => 15]);
$body = (string) $resp->getBody();

$pos = strpos($body, '__INITIAL_STATE__');
$eq = strpos($body, '=', $pos);
$jsonStart = $eq + 1;
while ($jsonStart < strlen($body) && ctype_space($body[$jsonStart])) {
    $jsonStart++;
}

// 大括号配平
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

echo 'JSON length: ' . strlen($json) . PHP_EOL;

// 尝试解码并定位错误
json_decode($json, true);
$err = json_last_error_msg();
echo 'Error: ' . $err . PHP_EOL;

if (function_exists('json_last_error_msg')) {
    // 用逐步截断找错误位置：尝试二分
    // 实际上用 JSON_THROW_ON_ERROR 配合 offset（PHP 7.3+ 支持 JSON_ERROR_... 但无 offset）
    // 这里采用分块尝试法
    $low = 1; $high = strlen($json);
    $lastGood = 0;
    // 不能简单二分因为 JSON 完整性。换个思路：扫描每个字符位置，找非法字符
    // 小红书常见问题：JSON 中包含未转义的控制字符或裸露的 </script>
    if (strpos($json, '</script>') !== false) {
        echo 'FOUND </script> inside JSON at: ' . strpos($json, '</script>') . PHP_EOL;
    }
    // 检查是否有未转义的换行/制表符在字符串中
    // 尝试 preg_replace 控制字符
    $cleaned = preg_replace('/[\x00-\x1F\x7F]/', ' ', $json);
    $decoded = json_decode($cleaned, true);
    echo 'After control-char removal: ' . (is_array($decoded) ? 'OK array(' . count($decoded) . ')' : json_last_error_msg()) . PHP_EOL;
    if (is_array($decoded)) {
        echo 'TOP KEYS: ' . implode(', ', array_keys($decoded)) . PHP_EOL;
    }
}
