<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

$short = $argv[1] ?? 'http://xhslink.com/o/wjWTM5ZvMj';
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
    'Referer' => 'https://www.xiaohongshu.com/',
];

$resp = $client->get($short, [RequestOptions::HEADERS => $headers]);
$hist = $resp->getHeader('X-Guzzle-Redirect-History');
$final = end($hist) ?: '';

echo 'FINAL: ' . $final . PHP_EOL;

// 从 final 的 404 链接中提取 originalUrl
$orig = '';
$q = parse_url($final, PHP_URL_QUERY);
if (is_string($q)) {
    parse_str($q, $p);
    $orig = $p['originalUrl'] ?? '';
}
echo 'ORIGINAL: ' . $orig . PHP_EOL;

if ($orig !== '') {
    $resp2 = $client->get($orig, [RequestOptions::HEADERS => $headers]);
    $body2 = (string) $resp2->getBody();
    echo 'STATUS2: ' . $resp2->getStatusCode() . PHP_EOL;
    echo 'LEN2: ' . strlen($body2) . PHP_EOL;
    $hist2 = $resp2->getHeader('X-Guzzle-Redirect-History');
    echo 'FINAL2: ' . (end($hist2) ?: '') . PHP_EOL;
    foreach (['__INITIAL_STATE__', 'noteDetail', 'imageList', 'note', 'og:image', 'og:title', 'title'] as $kw) {
        $pos = stripos($body2, $kw);
        echo $kw . ': ' . ($pos === false ? 'NOT FOUND' : '@' . $pos) . PHP_EOL;
    }
    // dump __INITIAL_STATE__ snippet
    $pos = stripos($body2, '__INITIAL_STATE__');
    if ($pos !== false) {
        echo '=== STATE SNIPPET (2000) ===' . PHP_EOL;
        echo substr($body2, $pos, 2000) . PHP_EOL;
    }
    // dump meta og
    if (preg_match('#<title[^>]*>(.*?)</title>#is', $body2, $m)) {
        echo 'TITLE TAG: ' . trim($m[1]) . PHP_EOL;
    }
    if (preg_match_all('#<meta[^>]+(og:image|og:title)[^>]*>#i', $body2, $mm)) {
        echo 'META OG: ' . implode(' | ', $mm[0]) . PHP_EOL;
    }
}
