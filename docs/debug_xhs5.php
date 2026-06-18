<?php
/**
 * 用更稳健的提取方式解析小红书 __INITIAL_STATE__ 并观察结构。
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
$redirect = $resp->getHeader('X-Guzzle-Redirect-History');
$finalUrl = end($redirect) ?: $url;

echo 'FINAL URL: ' . $finalUrl . PHP_EOL;
echo '风控命中: ' . ((strpos($finalUrl, '/404/sec_') !== false || strpos($finalUrl, 'source=xhs_sec_server') !== false) ? 'YES' : 'NO') . PHP_EOL;
echo 'BODY LEN: ' . strlen($body) . PHP_EOL;

// 稳健提取：定位 window.__INITIAL_STATE__= 后的 {，做字符串感知的大括号配平
$marker = 'window.__INITIAL_STATE__';
$mp = strpos($body, $marker);
if ($mp === false) {
    echo 'no marker' . PHP_EOL;
    exit;
}
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
echo 'EXTRACTED JSON LEN: ' . strlen($json) . PHP_EOL;

$data = json_decode($json, true);
echo 'DECODE: ' . (is_array($data) ? 'OK (' . count($data) . ' keys)' : json_last_error_msg()) . PHP_EOL;

if (is_array($data)) {
    echo 'TOP KEYS: ' . implode(', ', array_keys($data)) . PHP_EOL;
    // 递归查找 note / noteDetailMap / video
    $found = [];
    $walk = function ($v, $path) use (&$walk, &$found) {
        if (is_array($v)) {
            if (isset($v['noteId']) || isset($v['note']) || isset($v['noteDetailMap'])) {
                $found[] = $path;
            }
            foreach ($v as $k => $vv) {
                $walk($vv, $path . '.' . $k);
            }
        }
    };
    $walk($data, '$');
    echo PHP_EOL . 'Paths containing note-ish keys:' . PHP_EOL;
    foreach (array_slice($found, 0, 20) as $f) {
        echo '  ' . $f . PHP_EOL;
    }
}
