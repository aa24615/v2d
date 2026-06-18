<?php

declare(strict_types=1);

namespace Zyan\V2d\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Zyan\V2d\Contracts\AdapterInterface;
use Zyan\V2d\Exceptions\NetworkException;
use Zyan\V2d\Exceptions\ParseException;
use Zyan\V2d\Results\Result;

/**
 * 适配器基类，提供通用的 HTTP 请求、重定向跟踪与数据抽取能力。
 *
 * @package Zyan\V2d\Adapters
 */
abstract class AbstractAdapter implements AdapterInterface
{
    protected ClientInterface $client;

    /**
     * 默认请求头，子类可覆盖或扩展。
     *
     * @var array<string,string>
     */
    protected array $headers = [
        'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9',
    ];

    /**
     * 请求超时（秒）。
     *
     * @var int
     */
    protected int $timeout = 15;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client([
            RequestOptions::VERIFY => false,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 10,
                'strict' => false,
                'referer' => true,
                'track_redirects' => true,
            ],
        ]);
    }

    /**
     * 覆盖默认请求头（会整体替换）。
     *
     * @param array<string,string> $headers
     *
     * @return $this
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * 合并追加请求头（常用于注入 Cookie 绕过风控）。
     *
     * @param array<string,string> $headers
     *
     * @return $this
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * 设置 Cookie（用于绕过平台风控，可传入浏览器抓取的完整 Cookie 字符串）。
     *
     * @param string $cookie
     *
     * @return $this
     */
    public function withCookie(string $cookie): self
    {
        return $this->withHeaders(['Cookie' => $cookie]);
    }

    /**
     * 发送 GET 请求并返回响应体与最终落地链接。
     *
     * @param string $url
     * @param array<string,mixed> $extraHeaders
     *
     * @return array{0:string,1:string} [body, finalUrl]
     *
     * @throws NetworkException
     */
    protected function httpGet(string $url, array $extraHeaders = []): array
    {
        try {
            $response = $this->client->request('GET', $url, [
                RequestOptions::HEADERS => array_merge($this->headers, $extraHeaders),
                RequestOptions::TIMEOUT => $this->timeout,
            ]);
        } catch (GuzzleException $e) {
            throw new NetworkException(
                sprintf('请求失败: %s (url: %s)', $e->getMessage(), $url),
                $e->getCode(),
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new NetworkException(
                sprintf('请求返回错误状态码 %d (url: %s)', $statusCode, $url),
                $statusCode
            );
        }

        $body = (string) $response->getBody();

        // 解析最终落地链接
        $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
        $finalUrl = !empty($redirectHistory) ? end($redirectHistory) : $url;

        return [$body, (string) $finalUrl];
    }

    /**
     * 从 HTML 中匹配出一段 JSON 对象文本并解码。
     *
     * @param string $html
     * @param string $pattern 含有一个捕获组的正则
     *
     * @return array<string,mixed>|null
     *
     * @throws ParseException
     */
    protected function matchJson(string $html, string $pattern): ?array
    {
        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        $json = $matches[1] ?? '';

        // 处理转义的 Unicode 字符
        $decoded = $this->decodeUnicode($json);

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * 解码字符串中的 \uXXXX 转义序列。
     *
     * @param string $string
     *
     * @return string
     */
    protected function decodeUnicode(string $string): string
    {
        return preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static function (array $m): string {
                return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
            },
            $string
        ) ?? $string;
    }

    /**
     * 以锚点定位并提取一个完整的 JSON 对象（大括号配平）。
     *
     * 适用于数据内联在 HTML 中、且无法用简单正则匹配闭合的情况。
     *
     * @param string $html
     * @param string $anchor 锚点字符串，通常以 `{` 开头（如 `{"result":1,"fid"`）
     *
     * @return array<string,mixed>|null
     */
    protected function extractJsonObject(string $html, string $anchor): ?array
    {
        $pos = strpos($html, $anchor);
        if ($pos === false) {
            return null;
        }

        // 锚点以 { 开头则直接从锚点开始，否则向前回溯最近的 {
        if ($anchor !== '' && $anchor[0] === '{') {
            $start = $pos;
        } else {
            $start = strrpos(substr($html, 0, $pos + 1), '{');
            if ($start === false) {
                return null;
            }
        }

        $depth = 0;
        $inStr = false;
        $esc = false;
        $len = strlen($html);
        $end = $start;

        for ($i = $start; $i < $len; $i++) {
            $ch = $html[$i];
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

        if ($depth !== 0) {
            return null;
        }

        $json = substr($html, $start, $end - $start + 1);
        $json = str_replace('\\/', '/', $json);
        $json = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static function (array $m): string {
                return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
            },
            $json
        ) ?? $json;

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * 从 URL 中提取查询参数。
     *
     * @param string $url
     * @param string $name
     *
     * @return string
     */
    protected function getQuery(string $url, string $name): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query)) {
            return '';
        }

        parse_str($query, $params);

        return isset($params[$name]) && is_string($params[$name]) ? $params[$name] : '';
    }

    /**
     * 抓取并解析返回标准结果对象。
     *
     * @param string $url 分享链接（短链或长链）
     *
     * @return Result
     */
    abstract public function fetch(string $url): Result;
}
