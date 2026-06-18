<?php

declare(strict_types=1);

namespace Zyan\V2d\Tests;

use Zyan\V2d\Adapters\Douyin\DouyinAdapter;
use Zyan\V2d\Adapters\Kuaishou\KuaishouAdapter;
use Zyan\V2d\Adapters\Xiaohongshu\XiaohongshuAdapter;
use Zyan\V2d\Contracts\AdapterInterface;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\V2d;

/**
 * V2d 主入口单元测试。
 */
class V2dTest extends TestCase
{
    public function testDefaultAdaptersRegistered(): void
    {
        $v2d = new V2d();

        $adapters = $v2d->getAdapters();
        $this->assertCount(3, $adapters);

        $platforms = array_map(static function (AdapterInterface $adapter): string {
            return $adapter->getPlatform();
        }, $adapters);

        $this->assertContains('douyin', $platforms);
        $this->assertContains('xiaohongshu', $platforms);
        $this->assertContains('kuaishou', $platforms);
    }

    public function testResolveDouyin(): void
    {
        $v2d = new V2d();

        $this->assertInstanceOf(
            DouyinAdapter::class,
            $v2d->resolve('https://v.douyin.com/coTA27_qFBA/')
        );
    }

    public function testResolveXiaohongshu(): void
    {
        $v2d = new V2d();

        $this->assertInstanceOf(
            XiaohongshuAdapter::class,
            $v2d->resolve('http://xhslink.com/o/wjWTM5ZvMj')
        );
    }

    public function testResolveKuaishou(): void
    {
        $v2d = new V2d();

        $this->assertInstanceOf(
            KuaishouAdapter::class,
            $v2d->resolve('https://v.kuaishou.com/Jdl248ZA')
        );
    }

    public function testResolveUnsupportedThrowsException(): void
    {
        $v2d = new V2d();

        $this->expectException(InvalidUrlException::class);

        $v2d->resolve('https://www.example.com/some/unknown/link');
    }

    public function testFetchDispatchesToAdapter(): void
    {
        // 构造一个抖音图文 mock 响应
        $item = [
            'aweme_id' => '7234567890123',
            'desc' => '通过 V2d 入口抓取',
            'aweme_type' => 68,
            'author' => [
                'uid' => 'user123',
                'nickname' => '测试作者',
                'avatar_thumb' => ['url_list' => ['https://example.com/avatar.jpg']],
            ],
            'video' => ['cover' => ['url_list' => ['https://example.com/cover.jpg']]],
            'images' => [['url_list' => ['https://example.com/img1.jpg~tplv-tikx.image']]],
        ];
        $payload = ['loaderData' => ['video_(id)/page' => ['videoInfoRes' => ['item_list' => [$item]]]]];
        $html = '<html><head><script>window._ROUTER_DATA = '
            . json_encode($payload, JSON_UNESCAPED_UNICODE)
            . ';</script></head><body></body></html>';

        $client = $this->makeClient([
            $this->redirectResponse($html, 'https://www.iesdouyin.com/share/video/7234567890123/'),
        ]);

        $v2d = new V2d(null, $client);

        $result = $v2d->fetch('https://v.douyin.com/coTA27_qFBA/');

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertSame('douyin', $result->getPlatform());
        $this->assertSame('image', $result->getType());
    }

    public function testFetchArrayReturnsArray(): void
    {
        $item = [
            'aweme_id' => '7234567890123',
            'desc' => '数组形式',
            'aweme_type' => 68,
            'author' => ['uid' => 'u1', 'nickname' => 'n1', 'avatar_thumb' => ['url_list' => ['https://e.com/a.jpg']]],
            'video' => ['cover' => ['url_list' => ['https://e.com/c.jpg']]],
            'images' => [['url_list' => ['https://e.com/1.jpg~tplv-tikx.image']]],
        ];
        $payload = ['loaderData' => ['video_(id)/page' => ['videoInfoRes' => ['item_list' => [$item]]]]];
        $html = '<html><script>window._ROUTER_DATA = ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . ';</script></html>';

        $client = $this->makeClient([
            $this->redirectResponse($html, 'https://www.iesdouyin.com/share/video/7234567890123/'),
        ]);

        $array = (new V2d(null, $client))->fetchArray('https://v.douyin.com/abc/');

        $this->assertIsArray($array);
        $this->assertSame('image', $array['type']);
        $this->assertSame('douyin', $array['platform']);
        $this->assertArrayHasKey('images', $array);
    }

    public function testFetchJsonReturnsJsonString(): void
    {
        $item = [
            'aweme_id' => '7234567890123',
            'desc' => 'JSON 形式',
            'aweme_type' => 68,
            'author' => ['uid' => 'u1', 'nickname' => 'n1', 'avatar_thumb' => ['url_list' => ['https://e.com/a.jpg']]],
            'video' => ['cover' => ['url_list' => ['https://e.com/c.jpg']]],
            'images' => [['url_list' => ['https://e.com/1.jpg~tplv-tikx.image']]],
        ];
        $payload = ['loaderData' => ['video_(id)/page' => ['videoInfoRes' => ['item_list' => [$item]]]]];
        $html = '<html><script>window._ROUTER_DATA = ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . ';</script></html>';

        $client = $this->makeClient([
            $this->redirectResponse($html, 'https://www.iesdouyin.com/share/video/7234567890123/'),
        ]);

        $json = (new V2d(null, $client))->fetchJson('https://v.douyin.com/abc/');

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('image', $decoded['type']);
        // 中文应保持不转义
        $this->assertStringContainsString('JSON 形式', $json);
    }

    public function testRegisterCustomAdapter(): void
    {
        $v2d = new V2d([]); // 不注册任何适配器
        $this->assertCount(0, $v2d->getAdapters());

        $v2d->register(new DouyinAdapter());
        $this->assertCount(1, $v2d->getAdapters());
    }
}
