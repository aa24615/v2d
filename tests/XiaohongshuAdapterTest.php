<?php

declare(strict_types=1);

namespace Zyan\V2d\Tests;

use Zyan\V2d\Adapters\Xiaohongshu\XiaohongshuAdapter;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\Results\VideoResult;

/**
 * 小红书适配器单元测试。
 */
class XiaohongshuAdapterTest extends TestCase
{
    private const NOTE_ID = '6401234567890abcdef12345';

    /**
     * @param array<string,mixed> $noteOverrides
     *
     * @return string
     */
    private function makeHtml(array $noteOverrides = []): string
    {
        $note = array_merge([
            'noteId' => self::NOTE_ID,
            'title' => '小红书图文标题',
            'desc' => '这是详细描述',
            'type' => 'normal',
            'user' => [
                'userId' => 'user123',
                'nickname' => '小红书作者',
                'avatar' => '//sns-avatar.example.com/avatar.jpg',
            ],
            'imageList' => [
                ['urlDefault' => '//sns-webpic.example.com/img1.jpg'],
                ['urlDefault' => 'https://sns-webpic.example.com/img2.jpg'],
            ],
            'bgm' => [
                'id' => 'bgm001',
                'title' => '小红书背景音乐',
                'author' => '小红书音乐人',
                'url' => 'https://sns-video.example.com/bgm.mp3',
                'cover' => '//sns-webpic.example.com/bgm_cover.jpg',
            ],
        ], $noteOverrides);

        $payload = [
            'note' => [
                'noteDetailMap' => [
                    self::NOTE_ID => ['note' => $note],
                ],
            ],
        ];

        return '<html><head><script>window.__INITIAL_STATE__ = '
            . json_encode($payload, JSON_UNESCAPED_UNICODE)
            . ';</script></head><body></body></html>';
    }

    public function testSupports(): void
    {
        $adapter = new XiaohongshuAdapter();

        $this->assertTrue($adapter->supports('http://xhslink.com/o/wjWTM5ZvMj'));
        $this->assertTrue($adapter->supports('https://www.xiaohongshu.com/explore/' . self::NOTE_ID));
        $this->assertTrue($adapter->supports('https://www.xiaohongshu.com/discovery/item/' . self::NOTE_ID));
        $this->assertTrue($adapter->supports('https://www.xiaohongshu.com/note/' . self::NOTE_ID));
        $this->assertFalse($adapter->supports('https://v.douyin.com/coTA27_qFBA/'));
        $this->assertFalse($adapter->supports('https://www.kuaishou.com/short-video/123'));
    }

    public function testGetPlatform(): void
    {
        $this->assertSame('xiaohongshu', (new XiaohongshuAdapter())->getPlatform());
    }

    public function testFetchImage(): void
    {
        $html = $this->makeHtml();
        $finalUrl = 'https://www.xiaohongshu.com/explore/' . self::NOTE_ID;
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new XiaohongshuAdapter($client))->fetch('http://xhslink.com/o/wjWTM5ZvMj');

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertSame('image', $result->getType());
        $this->assertSame('xiaohongshu', $result->getPlatform());
        $this->assertSame($finalUrl, $result->getUrl());
        $this->assertSame('小红书图文标题', $result->getTitle());
        $this->assertSame('这是详细描述', $result->getDesc());
        $this->assertSame('小红书作者', $result->getAuthor()->getNickname());
        $this->assertSame('user123', $result->getAuthor()->getId());
        // // 开头的头像应补全为 https:
        $this->assertSame('https://sns-avatar.example.com/avatar.jpg', $result->getAuthor()->getAvatar());

        $images = $result->getImages();
        $this->assertCount(2, $images);
        $this->assertSame('https://sns-webpic.example.com/img1.jpg', $images[0]);
        $this->assertSame('https://sns-webpic.example.com/img2.jpg', $images[1]);

        $array = $result->toArray();
        $this->assertSame('image', $array['type']);
        $this->assertArrayHasKey('images', $array);

        // 背景音乐
        $music = $result->getMusic();
        $this->assertSame('bgm001', $music->getId());
        $this->assertSame('小红书背景音乐', $music->getTitle());
        $this->assertSame('小红书音乐人', $music->getAuthor());
        $this->assertSame('https://sns-video.example.com/bgm.mp3', $music->getUrl());
        // // 开头的封面应补全为 https:
        $this->assertSame('https://sns-webpic.example.com/bgm_cover.jpg', $music->getCover());
    }

    public function testFetchVideo(): void
    {
        $html = $this->makeHtml([
            'type' => 'video',
            'imageList' => [],
            'video' => [
                'media' => [
                    'stream' => [
                        'h264' => [
                            ['masterUrl' => 'https://sns-video.example.com/video.mp4'],
                        ],
                    ],
                ],
            ],
        ]);
        $finalUrl = 'https://www.xiaohongshu.com/explore/' . self::NOTE_ID;
        $client = $this->makeClient([$this->redirectResponse($html, $finalUrl)]);

        $result = (new XiaohongshuAdapter($client))->fetch('http://xhslink.com/o/223L8fs1OoG');

        $this->assertInstanceOf(VideoResult::class, $result);
        $this->assertSame('video', $result->getType());
        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        $this->assertSame('https://sns-video.example.com/video.mp4', $videos[0]['url']);
        $this->assertSame('https://sns-video.example.com/video.mp4', $result->getVideoUrl());
    }

    public function testFetchInvalidUrlThrowsException(): void
    {
        $this->expectException(InvalidUrlException::class);

        (new XiaohongshuAdapter())->fetch('https://www.kuaishou.com/short-video/123');
    }
}
