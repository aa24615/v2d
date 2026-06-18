<?php

declare(strict_types=1);

namespace Zyan\V2d\Tests;

use Zyan\V2d\Adapters\Kuaishou\KuaishouAdapter;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\Results\VideoResult;

/**
 * 快手适配器单元测试。
 */
class KuaishouAdapterTest extends TestCase
{
    /**
     * @param array<string,mixed> $photoOverrides
     *
     * @return string
     */
    private function makeHtml(array $photoOverrides = []): string
    {
        $photo = array_merge([
            'photoId' => '3x1234567890',
            'caption' => "快手图文标题\n这是描述",
            'coverUrl' => 'https://example.com/cover.jpg',
            'author' => [
                'eid' => 'author123',
                'name' => '快手作者',
                'headerUrl' => 'https://example.com/avatar.jpg',
            ],
            'atlas' => [
                'cdn' => [
                    'https://example.com/img1.jpg',
                    'https://example.com/img2.jpg',
                ],
            ],
            'soundTrack' => [
                'id' => 28886255390,
                'name' => '快手原声',
                'artist' => '快手作者',
                'audioUrls' => [
                    ['cdn' => 'hw.a.yximgs.com', 'url' => 'https://hw.a.yximgs.com/ost.m4a'],
                ],
                'imageUrls' => [
                    ['cdn' => 'hw.a.yximgs.com', 'url' => 'https://hw.a.yximgs.com/ost.jpg'],
                ],
            ],
        ], $photoOverrides);

        $payload = [
            'defaultClient' => [
                'visionVideoDetail' => [
                    'photo' => $photo,
                ],
            ],
        ];

        return '<html><head><script>window.__APOLLO_STATE__ = '
            . json_encode($payload, JSON_UNESCAPED_UNICODE)
            . ';</script></head><body></body></html>';
    }

    public function testSupports(): void
    {
        $adapter = new KuaishouAdapter();

        $this->assertTrue($adapter->supports('https://v.kuaishou.com/Jdl248ZA'));
        $this->assertTrue($adapter->supports('https://www.kuaishou.com/short-video/3x1234567890'));
        $this->assertTrue($adapter->supports('https://www.kuaishou.com/f/3x1234567890'));
        $this->assertFalse($adapter->supports('https://v.douyin.com/coTA27_qFBA/'));
        $this->assertFalse($adapter->supports('http://xhslink.com/o/wjWTM5ZvMj'));
    }

    public function testGetPlatform(): void
    {
        $this->assertSame('kuaishou', (new KuaishouAdapter())->getPlatform());
    }

    public function testFetchImage(): void
    {
        $html = $this->makeHtml();
        $finalUrl = 'https://www.kuaishou.com/short-video/3x1234567890';
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new KuaishouAdapter($client))->fetch('https://v.kuaishou.com/Jdl248ZA');

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertSame('image', $result->getType());
        $this->assertSame('kuaishou', $result->getPlatform());
        $this->assertSame($finalUrl, $result->getUrl());
        $this->assertSame('快手图文标题', $result->getTitle());
        $this->assertSame("快手图文标题\n这是描述", $result->getDesc());
        $this->assertSame('快手作者', $result->getAuthor()->getNickname());
        $this->assertSame('author123', $result->getAuthor()->getId());
        $this->assertSame('https://example.com/avatar.jpg', $result->getAuthor()->getAvatar());
        $this->assertSame('https://example.com/cover.jpg', $result->getCover());

        $images = $result->getImages();
        $this->assertCount(2, $images);
        $this->assertSame('https://example.com/img1.jpg', $images[0]);
        $this->assertSame('https://example.com/img2.jpg', $images[1]);

        $array = $result->toArray();
        $this->assertSame('image', $array['type']);
        $this->assertArrayHasKey('images', $array);

        // 背景音乐
        $music = $result->getMusic();
        $this->assertSame('28886255390', $music->getId());
        $this->assertSame('快手原声', $music->getTitle());
        $this->assertSame('快手作者', $music->getAuthor());
        $this->assertSame('https://hw.a.yximgs.com/ost.m4a', $music->getUrl());
        $this->assertSame('https://hw.a.yximgs.com/ost.jpg', $music->getCover());
    }

    public function testFetchVideo(): void
    {
        $html = $this->makeHtml([
            'caption' => '快手视频标题',
            'atlas' => null,
            'mainMvUrls' => [
                ['url' => 'https://example.com/video_hd.mp4', 'qualityType' => 'hd'],
            ],
            'photoUrl' => 'https://example.com/photo.mp4',
        ]);
        $finalUrl = 'https://www.kuaishou.com/short-video/3x1234567890';
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new KuaishouAdapter($client))->fetch('https://v.kuaishou.com/Jdl248ZA');

        $this->assertInstanceOf(VideoResult::class, $result);
        $this->assertSame('video', $result->getType());
        $videos = $result->getVideos();
        // mainMvUrls 1 个 + photoUrl 1 个
        $this->assertCount(2, $videos);
        $this->assertSame('https://example.com/video_hd.mp4', $videos[0]['url']);
        $this->assertSame('https://example.com/photo.mp4', $videos[1]['url']);
        $this->assertSame('https://example.com/video_hd.mp4', $result->getVideoUrl());
    }

    public function testFetchImageFromMobilePage(): void
    {
        // 模拟 v.m.chenzhongtech.com 移动端页面内联的 JSON 数据块
        $block = [
            'result' => 1,
            'fid' => 2692937949,
            'atlas' => [
                'cdn' => ['p23.a.yximgs.com', 'p2.a.yximgs.com'],
                'cdnList' => [['cdn' => 'p23.a.yximgs.com']],
                'list' => ['/ufile/atlas/IMG_0.jpg', '/ufile/atlas/IMG_1.jpg'],
            ],
            'photo' => [
                'caption' => '移动端图文标题',
                'coverUrls' => [['url' => 'https://p2.a.yximgs.com/cover.jpg']],
                'mainMvUrls' => [],
                'userName' => '移动作者',
                'kwaiId' => 'kwai123',
                'headUrl' => 'https://p2.a.yximgs.com/head.jpg',
            ],
        ];
        $html = '<html><script>' . json_encode($block, JSON_UNESCAPED_UNICODE) . '</script></html>';
        $finalUrl = 'https://v.m.chenzhongtech.com/fw/photo/3x1234567890';
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new KuaishouAdapter($client))->fetch('https://v.kuaishou.com/Jdl248ZA');

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertSame('image', $result->getType());
        $this->assertSame('kuaishou', $result->getPlatform());
        $this->assertSame('移动端图文标题', $result->getTitle());
        $this->assertSame('移动作者', $result->getAuthor()->getNickname());
        $this->assertSame('kwai123', $result->getAuthor()->getId());
        $this->assertSame('https://p2.a.yximgs.com/head.jpg', $result->getAuthor()->getAvatar());
        $this->assertSame('https://p2.a.yximgs.com/cover.jpg', $result->getCover());

        $images = $result->getImages();
        $this->assertCount(2, $images);
        $this->assertSame('https://p23.a.yximgs.com/ufile/atlas/IMG_0.jpg', $images[0]);
        $this->assertSame('https://p23.a.yximgs.com/ufile/atlas/IMG_1.jpg', $images[1]);
    }

    public function testFetchVideoFromMobilePage(): void
    {
        $block = [
            'result' => 1,
            'fid' => 123,
            'atlas' => ['cdn' => [], 'list' => []],
            'photo' => [
                'caption' => '移动端视频',
                'coverUrls' => [['url' => 'https://p2.a.yximgs.com/cover.jpg']],
                'mainMvUrls' => [['url' => 'https://p2.a.yximgs.com/video.mp4', 'qualityType' => 'hd']],
                'userName' => '视频作者',
                'kwaiId' => 'kwai456',
                'headUrl' => 'https://p2.a.yximgs.com/head.jpg',
            ],
        ];
        $html = '<html><script>' . json_encode($block, JSON_UNESCAPED_UNICODE) . '</script></html>';
        $finalUrl = 'https://v.m.chenzhongtech.com/fw/photo/3x1234567890';
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new KuaishouAdapter($client))->fetch('https://v.kuaishou.com/Jdl248ZA');

        $this->assertInstanceOf(VideoResult::class, $result);
        $this->assertSame('video', $result->getType());
        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        $this->assertSame('https://p2.a.yximgs.com/video.mp4', $videos[0]['url']);
        $this->assertSame('https://p2.a.yximgs.com/video.mp4', $result->getVideoUrl());
    }

    public function testFetchInvalidUrlThrowsException(): void
    {
        $this->expectException(InvalidUrlException::class);

        (new KuaishouAdapter())->fetch('https://v.douyin.com/coTA27_qFBA/');
    }
}
