<?php

declare(strict_types=1);

namespace Zyan\V2d\Adapters\Xiaohongshu;

use Zyan\V2d\Adapters\AbstractAdapter;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Exceptions\ParseException;
use Zyan\V2d\Results\Author;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\Results\Result;
use Zyan\V2d\Results\VideoResult;

/**
 * 小红书（Xiaohongshu / RED）抓取适配器。
 *
 * 支持以下分享链接形式：
 *  - 短链: http(s)://xhslink.com/o/xxxxx 或 a/xxxxx
 *  - 长链: https://www.xiaohongshu.com/explore/{note_id}
 *  - 长链: https://www.xiaohongshu.com/discovery/item/{note_id}
 *  - 长链: https://www.xiaohongshu.com/note/{note_id}
 *
 * 通过详情页 window.__INITIAL_STATE__ 解析图文与视频。
 *
 * @package Zyan\V2d\Adapters\Xiaohongshu
 */
class XiaohongshuAdapter extends AbstractAdapter
{
    public const PLATFORM = 'xiaohongshu';

    /**
     * {@inheritdoc}
     */
    public function getPlatform(): string
    {
        return self::PLATFORM;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $url): bool
    {
        $url = trim($url);

        return (bool) preg_match('#xhslink\.com/#i', $url)
            || (bool) preg_match('#xiaohongshu\.com/(?:explore|discovery/item|note|user/profile)/#i', $url);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(string $url): Result
    {
        $url = trim($url);
        if (!$this->supports($url)) {
            throw new InvalidUrlException(sprintf('无法识别的小红书分享链接: %s', $url));
        }

        [$body, $finalUrl] = $this->httpGet($url, [
            // 小红书对 referer 较敏感，携带其自身域名可提高成功率
            'Referer' => 'https://www.xiaohongshu.com/',
        ]);

        $noteId = $this->extractNoteId($finalUrl) ?: $this->extractNoteId($url);
        $note = $this->extractNote($body, $noteId);

        return $this->buildResult($note, $finalUrl ?: $url);
    }

    /**
     * 从链接中提取笔记 ID。
     *
     * @param string $url
     *
     * @return string
     */
    protected function extractNoteId(string $url): string
    {
        if (preg_match('#/(?:explore|discovery/item|note)/([a-zA-Z0-9]{24})#i', $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * 从页面 HTML 中解析出笔记原始数据。
     *
     * @param string $html
     * @param string $noteId
     *
     * @return array<string,mixed>
     *
     * @throws ParseException
     */
    protected function extractNote(string $html, string $noteId): array
    {
        $data = $this->matchJson(
            $html,
            '/window\.__INITIAL_STATE__\s*=\s*(\{.*?\})\s*;?\s*<\/script>/s'
        );

        if (!is_array($data)) {
            // 尝试非闭合写法
            $data = $this->matchJson(
                $html,
                '/window\.__INITIAL_STATE__\s*=\s*(\{.*\})\s*;/s'
            );
        }

        if (!is_array($data)) {
            throw new ParseException('无法从小红书页面解析出笔记数据，可能页面结构已变更或被风控。');
        }

        $note = $this->findNoteInState($data, $noteId);

        if (empty($note)) {
            throw new ParseException('小红书页面未找到对应笔记数据。');
        }

        return $note;
    }

    /**
     * @param array<string,mixed> $state
     * @param string $noteId
     *
     * @return array<string,mixed>
     */
    protected function findNoteInState(array $state, string $noteId): array
    {
        $note = $state['note'] ?? $state['noteDetailMap'] ?? null;

        // 标准结构：state.note.noteDetailMap.{noteId}.note
        if (is_array($note) && isset($note['noteDetailMap']) && is_array($note['noteDetailMap'])) {
            if ($noteId !== '' && isset($note['noteDetailMap'][$noteId]['note'])) {
                return $note['noteDetailMap'][$noteId]['note'];
            }

            // 取第一个可用笔记
            foreach ($note['noteDetailMap'] as $entry) {
                if (is_array($entry) && isset($entry['note']) && is_array($entry['note'])) {
                    return $entry['note'];
                }
            }
        }

        // 兼容直接存放 noteDetailMap 的情况
        if (is_array($note)) {
            foreach ($note as $entry) {
                if (is_array($entry) && isset($entry['note']) && is_array($entry['note'])) {
                    return $entry['note'];
                }
            }
        }

        return [];
    }

    /**
     * 将笔记数据转换为标准结果对象。
     *
     * @param array<string,mixed> $note
     * @param string $url
     *
     * @return Result
     */
    protected function buildResult(array $note, string $url): Result
    {
        $imageList = $this->extractImages($note);
        $videoList = $this->extractVideos($note);
        $isImage = !empty($imageList) && empty($videoList);

        $common = [
            'platform' => self::PLATFORM,
            'url' => $url,
            'title' => (string) ($note['title'] ?? ''),
            'desc' => (string) ($note['desc'] ?? ''),
            'cover' => $this->extractCover($note, $imageList),
            'author' => $this->extractAuthor($note),
            'raw' => $note,
        ];

        if ($isImage) {
            return new ImageResult(array_merge($common, ['images' => $imageList]));
        }

        return new VideoResult(array_merge($common, ['videos' => $videoList]));
    }

    /**
     * @param array<string,mixed> $note
     *
     * @return array<int,string>
     */
    protected function extractImages(array $note): array
    {
        $imageList = $note['imageList'] ?? $note['image_list'] ?? null;
        if (!is_array($imageList)) {
            return [];
        }

        $list = [];
        foreach ($imageList as $image) {
            if (!is_array($image)) {
                continue;
            }

            $url = (string) ($image['urlDefault'] ?? $image['url'] ?? $image['original'] ?? '');
            if ($url === '' && isset($image['urlList']) && is_array($image['urlList'])) {
                $url = (string) ($image['urlList'][0] ?? '');
            }

            if ($url !== '') {
                $list[] = $this->normalizeUrl($url);
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @param array<string,mixed> $note
     *
     * @return array<int,array<string,mixed>>
     */
    protected function extractVideos(array $note): array
    {
        $video = $note['video'] ?? null;
        if (!is_array($video)) {
            return [];
        }

        $videos = [];

        $media = $video['media'] ?? $video;
        $streams = [];

        if (isset($media['stream']) && is_array($media['stream'])) {
            $streams = $media['stream'];
        }

        foreach (['h264', 'h265', 'h266', 'av1'] as $codec) {
            if (!isset($streams[$codec]) || !is_array($streams[$codec])) {
                continue;
            }

            foreach ($streams[$codec] as $idx => $stream) {
                if (!is_array($stream)) {
                    continue;
                }

                $url = (string) ($stream['masterUrl'] ?? $stream['backupUrls'][0] ?? '');
                if ($url === '') {
                    continue;
                }

                $videos[] = [
                    'url' => $this->normalizeUrl($url),
                    'quality' => $codec . '-' . ((string) ($idx + 1)),
                    'format' => 'mp4',
                ];
            }
        }

        // consumer origin video
        $origin = $video['consumer'] ?? null;
        if (is_array($origin) && isset($origin['originVideoKey'])) {
            $url = (string) ($origin['originVideoKey'] ?? '');
            if ($url !== '') {
                $videos[] = [
                    'url' => $url,
                    'quality' => 'origin',
                    'format' => 'mp4',
                ];
            }
        }

        return $videos;
    }

    /**
     * @param array<string,mixed> $note
     * @param array<int,string> $images
     *
     * @return string
     */
    protected function extractCover(array $note, array $images): string
    {
        $imageList = $note['imageList'] ?? null;
        if (is_array($imageList)) {
            $first = $imageList[0] ?? null;
            if (is_array($first)) {
                $cover = (string) ($first['urlDefault'] ?? $first['url'] ?? '');
                if ($cover !== '') {
                    return $this->normalizeUrl($cover);
                }
            }
        }

        return $images[0] ?? '';
    }

    /**
     * @param array<string,mixed> $note
     *
     * @return Author
     */
    protected function extractAuthor(array $note): Author
    {
        $user = $note['user'] ?? $note['userInfo'] ?? [];
        if (!is_array($user)) {
            $user = [];
        }

        $avatar = (string) ($user['avatar'] ?? $user['avatarHd'] ?? $user['avatarBig'] ?? '');

        return new Author(
            (string) ($user['userId'] ?? $user['userid'] ?? $user['nickName'] ?? ''),
            (string) ($user['nickname'] ?? $user['nickName'] ?? ''),
            $this->normalizeUrl($avatar)
        );
    }

    /**
     * 规范化小红书资源地址，补全协议与查询参数。
     *
     * @param string $url
     *
     * @return string
     */
    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // 小红书部分图片地址为 //sns-webpic-...
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        return $url;
    }
}
