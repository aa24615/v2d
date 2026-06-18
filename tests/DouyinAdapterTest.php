<?php

declare(strict_types=1);

namespace Zyan\V2d\Tests;

use Zyan\V2d\Adapters\Douyin\DouyinAdapter;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\Results\VideoResult;

/**
 * 抖音适配器单元测试。
 *
 * 使用 Mock HTTP 客户端注入模拟页面，验证图文/视频解析逻辑。
 */
class DouyinAdapterTest extends TestCase
{
    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function makeItem(array $overrides = []): array
    {
        return array_merge([
            'aweme_id' => '7234567890123',
            'desc' => "测试图文标题\n这是详细描述",
            'aweme_type' => 68,
            'author' => [
                'uid' => 'user123',
                'nickname' => '测试作者',
                'avatar_thumb' => ['url_list' => ['https://example.com/avatar.jpg']],
            ],
            'video' => [
                'cover' => ['url_list' => ['https://example.com/cover.jpg']],
            ],
            'images' => [
                ['url_list' => ['https://example.com/img1.jpg~tplv-tikx.image']],
                ['url_list' => ['https://example.com/img2.jpg~tplv-tikx.image']],
            ],
        ], $overrides);
    }

    private function makeHtml(array $item): string
    {
        $payload = ['loaderData' => ['video_(id)/page' => ['videoInfoRes' => ['item_list' => [$item]]]]];

        return '<html><head><script>window._ROUTER_DATA = '
            . json_encode($payload, JSON_UNESCAPED_UNICODE)
            . ';</script></head><body></body></html>';
    }

    public function testSupports(): void
    {
        $adapter = new DouyinAdapter();

        $this->assertTrue($adapter->supports('https://v.douyin.com/coTA27_qFBA/'));
        $this->assertTrue($adapter->supports('https://www.douyin.com/video/7234567890123'));
        $this->assertTrue($adapter->supports('https://www.douyin.com/note/7234567890123'));
        $this->assertTrue($adapter->supports('https://www.iesdouyin.com/share/video/7234567890123/'));
        $this->assertFalse($adapter->supports('https://www.kuaishou.com/short-video/123'));
        $this->assertFalse($adapter->supports('http://xhslink.com/o/wjWTM5ZvMj'));
    }

    public function testGetPlatform(): void
    {
        $this->assertSame('douyin', (new DouyinAdapter())->getPlatform());
    }

    public function testFetchImage(): void
    {
        $html = $this->makeHtml($this->makeItem());
        $finalUrl = 'https://www.iesdouyin.com/share/video/7234567890123/';
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new DouyinAdapter($client))->fetch('https://v.douyin.com/coTA27_qFBA/');

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertSame('image', $result->getType());
        $this->assertSame('douyin', $result->getPlatform());
        $this->assertSame($finalUrl, $result->getUrl());
        $this->assertSame('测试图文标题', $result->getTitle());
        $this->assertSame("测试图文标题\n这是详细描述", $result->getDesc());
        $this->assertSame('测试作者', $result->getAuthor()->getNickname());
        $this->assertSame('user123', $result->getAuthor()->getId());
        $this->assertSame('https://example.com/avatar.jpg', $result->getAuthor()->getAvatar());
        $this->assertSame('https://example.com/cover.jpg', $result->getCover());

        $images = $result->getImages();
        $this->assertCount(2, $images);
        $this->assertStringContainsString('tplv-origin', $images[0]);
        $this->assertStringContainsString('img1.jpg', $images[0]);
        $this->assertStringContainsString('img2.jpg', $images[1]);

        // 序列化校验
        $array = $result->toArray();
        $this->assertSame('image', $array['type']);
        $this->assertArrayHasKey('images', $array);
    }

    public function testFetchVideo(): void
    {
        $item = $this->makeItem([
            'aweme_type' => 0,
            'images' => null,
            'video' => [
                'cover' => ['url_list' => ['https://example.com/cover.jpg']],
                'play_addr' => ['url_list' => ['https://example.com/video_playwm.mp4']],
            ],
        ]);
        $html = $this->makeHtml($item);
        $finalUrl = 'https://www.iesdouyin.com/share/video/7234567890123/';
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new DouyinAdapter($client))->fetch('https://www.douyin.com/video/7234567890123');

        $this->assertInstanceOf(VideoResult::class, $result);
        $this->assertSame('video', $result->getType());
        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        // playwm 应被替换为 play
        $this->assertSame('https://example.com/video_play.mp4', $videos[0]['url']);
        $this->assertSame('https://example.com/video_play.mp4', $result->getVideoUrl());
    }

    public function testFetchInvalidUrlThrowsException(): void
    {
        $this->expectException(InvalidUrlException::class);

        (new DouyinAdapter())->fetch('https://www.kuaishou.com/short-video/123');
    }
}
