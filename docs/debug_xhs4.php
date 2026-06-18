<?php
/**
 * 检查 __INITIAL_STATE__ 周围的原始文本。
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

// 所有 __INITIAL_STATE__ 出现位置
$needle = '__INITIAL_STATE__';
$offset = 0;
$positions = [];
while (($p = strpos($body, $needle, $offset)) !== false) {
    $positions[] = $p;
    $offset = $p + 1;
}
echo 'Occurrences of __INITIAL_STATE__: ' . count($positions) . PHP_EOL;
foreach ($positions as $i => $p) {
    echo PHP_EOL . '===== #' . $i . ' at pos ' . $p . ' =====' . PHP_EOL;
    echo 'context[-30:+200]: ' . PHP_EOL;
    echo substr($body, max(0, $p - 30), 230) . PHP_EOL;
}

// 也找所有 </script> 位置
echo PHP_EOL . '===== </script> count =====' . PHP_EOL;
echo substr_count($body, '</script>') . PHP_EOL;
