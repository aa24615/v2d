<?php
/**
 * 定位 __INITIAL_STATE__ JSON 解析失败的根本原因。
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

$marker = 'window.__INITIAL_STATE__';
$mp = strpos($body, $marker);
$eq = strpos($body, '=', $mp);
$start = $eq + 1;
while ($start < strlen($body) && ctype_space($body[$start])) {
    $start++;
}

$depth = 0; $inStr = false; $esc = false; $len = strlen($body); $end = $start;
for ($i = $start; $i < $len; $i++) {
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

$json = substr($body, $start, $end - $start + 1);
$json = str_replace('\\/', '/', $json);

echo 'LEN: ' . strlen($json) . PHP_EOL;

// 检测各种非 JSON 内容
echo 'has undefined: ' . (preg_match('/\bundefined\b/', $json) ? 'YES' : 'no') . PHP_EOL;
echo 'has NaN: ' . (preg_match('/\bNaN\b/', $json) ? 'YES' : 'no') . PHP_EOL;
echo 'has Infinity: ' . (preg_match('/\bInfinity\b/', $json) ? 'YES' : 'no') . PHP_EOL;
echo 'literal TAB count: ' . substr_count($json, "\t") . PHP_EOL;
echo 'literal LF count: ' . substr_count($json, "\n") . PHP_EOL;
echo 'literal CR count: ' . substr_count($json, "\r") . PHP_EOL;

// 找第一个出现的控制字符（在字符串内或外）
for ($i = 0; $i < strlen($json); $i++) {
    $o = ord($json[$i]);
    if ($o < 0x20 && $json[$i] !== "\t" && $json[$i] !== "\n" && $json[$i] !== "\r") {
        echo 'CONTROL CHAR at offset ' . $i . ' ord=' . $o . PHP_EOL;
        echo 'context: ' . addcslashes(substr($json, max(0, $i - 30), 60), "\t\n\r") . PHP_EOL;
        break;
    }
}

// 试着把控制字符转义后再解码
$clean = preg_replace_callback('/[\x00-\x1F]/', static function ($m) {
    return '\\u' . sprintf('%04x', ord($m[0]));
}, $json);
$d = json_decode($clean, true);
echo 'after control-char escape: ' . (is_array($d) ? 'OK (' . count($d) . ' keys)' : json_last_error_msg()) . PHP_EOL;

// 试 undefined -> null
$clean2 = preg_replace('/\bundefined\b/', 'null', $clean);
$d2 = json_decode($clean2, true);
echo 'after undefined->null: ' . (is_array($d2) ? 'OK (' . count($d2) . ' keys)' : json_last_error_msg()) . PHP_EOL;

if (is_array($d2)) {
    echo 'TOP KEYS: ' . implode(', ', array_keys($d2)) . PHP_EOL;
}
