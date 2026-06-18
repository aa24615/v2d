<?php

declare(strict_types=1);

namespace Zyan\V2d\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * V2d SDK 测试基类。
 *
 * @package Zyan\V2d\Tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * 构造一个注入 Mock 响应的 Guzzle 客户端。
     *
     * @param array<int,array{body:string,status?:int,headers?:array<string,string|string[]>}> $responses
     *
     * @return Client
     */
    protected function makeClient(array $responses): Client
    {
        $mockResponses = [];
        foreach ($responses as $response) {
            $mockResponses[] = new Response(
                $response['status'] ?? 200,
                $response['headers'] ?? [],
                $response['body'] ?? ''
            );
        }

        $handler = HandlerStack::create(new MockHandler($mockResponses));

        return new Client([
            'handler' => $handler,
            'http_errors' => false,
        ]);
    }

    /**
     * 构造一个模拟最终落地链接的响应。
     *
     * @param string $body
     * @param string $finalUrl
     *
     * @return array{body:string,headers:array<string,string[]>}
     */
    protected function redirectResponse(string $body, string $finalUrl): array
    {
        return [
            'body' => $body,
            'headers' => ['X-Guzzle-Redirect-History' => [$finalUrl]],
        ];
    }
}
