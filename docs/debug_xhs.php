<?php
/**
 * 调试小红书页面结构。
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

$url = 'http://xhslink.com/o/6cQi47pyi36';

$client = new Client([
    RequestOptions::VERIFY => false,
    RequestOptions::HTTP_ERRORS => false,
    RequestOptions::ALLOW_REDIRECTS => [
        'max' => 10,
        'track_redirects' => true,
    ],
]);

$headers = [
    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language' => 'zh-CN,zh;q=0.9',
    'Referer' => 'https://www.xiaohongshu.com/',
];

try {
    $resp = $client->request('GET', $url, [
        RequestOptions::HEADERS => $headers,
        RequestOptions::TIMEOUT => 15,
    ]);
} catch (\Throwable $e) {
    echo 'REQUEST ERROR: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo 'STATUS: ' . $resp->getStatusCode() . PHP_EOL;
$redirect = $resp->getHeader('X-Guzzle-Redirect-History');
echo 'FINAL URL: ' . (end($redirect) ?: $url) . PHP_EOL;
$body = (string) $resp->getBody();
echo 'BODY LENGTH: ' . strlen($body) . PHP_EOL;
echo PHP_EOL . '===== HEAD (3000 chars) =====' . PHP_EOL;
echo substr($body, 0, 3000) . PHP_EOL;

echo PHP_EOL . '===== markers =====' . PHP_EOL;
foreach (['__INITIAL_STATE__', '__NUXT__', 'window.__', 'noteDetailMap', 'note', 'initialState', 'video', '"type":"video"', 'sec_'] as $m) {
    echo sprintf('%-25s pos=%d', $m, strpos($body, $m)) . PHP_EOL;
}
