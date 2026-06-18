<?php

declare(strict_types=1);

namespace Zyan\V2d\Adapters\Kuaishou;

use Zyan\V2d\Adapters\AbstractAdapter;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Exceptions\ParseException;
use Zyan\V2d\Results\Author;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\Results\Result;
use Zyan\V2d\Results\VideoResult;

/**
 * 快手（Kuaishou）抓取适配器。
 *
 * 支持以下分享链接形式：
 *  - 短链: https://v.kuaishou.com/xxxxx
 *  - 长链: https://www.kuaishou.com/short-video/{photo_id}
 *  - 长链: https://www.kuaishou.com/f/{photo_id}
 *  - 移动端: https://v.m.chenzhongtech.com/fw/photo/{photo_id}
 *
 * 短链通常会重定向到移动端页面 v.m.chenzhongtech.com，
 * 其图集数据内联在形如 `{"result":1,"fid":...,"atlas":{...},"photo":{...}}` 的 JSON 块中；
 * 同时兼容 www.kuaishou.com 详情页的 __APOLLO_STATE__ / __PAGE_STATE__ 结构。
 *
 * @package Zyan\V2d\Adapters\Kuaishou
 */
class KuaishouAdapter extends AbstractAdapter
{
    public const PLATFORM = 'kuaishou';

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

        return (bool) preg_match('#v\.kuaishou\.com/#i', $url)
            || (bool) preg_match('#kuaishou\.com/(?:short-video|f|new-reco|profile)/#i', $url)
            || (bool) preg_match('#chenzhongtech\.com/fw/photo/#i', $url);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(string $url): Result
    {
        $url = trim($url);
        if (!$this->supports($url)) {
            throw new InvalidUrlException(sprintf('无法识别的快手分享链接: %s', $url));
        }

        [$body, $finalUrl] = $this->httpGet($url, [
            'Referer' => 'https://www.kuaishou.com/',
        ]);

        $photo = $this->extractPhoto($body);

        return $this->buildResult($photo, $finalUrl ?: $url);
    }

    /**
     * 从页面 HTML 中解析出作品原始数据（含 atlas）。
     *
     * @param string $html
     *
     * @return array<string,mixed>
     *
     * @throws ParseException
     */
    protected function extractPhoto(string $html): array
    {
        // 1) 移动端页面：内联的 {"result":1,"fid":...,"atlas":{...},"photo":{...}}
        $block = $this->extractJsonObject($html, '{"result":1,"fid"');
        if (is_array($block)) {
            $photo = $block['photo'] ?? [];
            if (!is_array($photo)) {
                $photo = [];
            }
            if (isset($block['atlas']) && is_array($block['atlas'])) {
                $photo['atlas'] = $block['atlas'];
            }
            if (!empty($photo)) {
                return $photo;
            }
        }

        // 2) www.kuaishou.com 详情页：__APOLLO_STATE__
        $apollo = $this->matchJson(
            $html,
            '/window\.__APOLLO_STATE__\s*=\s*(\{.*?\})\s*;?\s*<\/script>/s'
        );
        if (is_array($apollo)) {
            $photo = $this->findPhotoInApollo($apollo);
            if (!empty($photo)) {
                return $photo;
            }
        }

        // 3) __PAGE_STATE__
        $pageState = $this->matchJson(
            $html,
            '/window\.__PAGE_STATE__\s*=\s*(\{.*?\})\s*;?\s*<\/script>/s'
        );
        if (is_array($pageState)) {
            $photo = $this->findPhotoInState($pageState);
            if (!empty($photo)) {
                return $photo;
            }
        }

        throw new ParseException('无法从快手页面解析出作品数据，可能页面结构已变更或被风控。');
    }

    /**
     * @param array<string,mixed> $apollo
     *
     * @return array<string,mixed>
     */
    protected function findPhotoInApollo(array $apollo): array
    {
        $defaultClient = $apollo['defaultClient'] ?? $apollo['ROOT_MUTATION'] ?? [];
        if (!is_array($defaultClient)) {
            $defaultClient = $apollo;
        }

        $detail = $defaultClient['visionVideoDetail'] ?? null;
        if (is_array($detail) && isset($detail['photo']) && is_array($detail['photo'])) {
            return $detail['photo'];
        }

        foreach ($defaultClient as $value) {
            if (!is_array($value)) {
                continue;
            }
            if (isset($value['photo']) && is_array($value['photo'])) {
                return $value['photo'];
            }
            if (isset($value['photoId']) && (isset($value['caption']) || isset($value['coverUrl']))) {
                return $value;
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $state
     *
     * @return array<string,mixed>
     */
    protected function findPhotoInState(array $state): array
    {
        $detail = $state['visionVideoDetail'] ?? $state['videoDetail'] ?? null;
        if (is_array($detail) && isset($detail['photo']) && is_array($detail['photo'])) {
            return $detail['photo'];
        }

        if (isset($state['photo']) && is_array($state['photo'])) {
            return $state['photo'];
        }

        return $this->findPhotoInApollo($state);
    }

    /**
     * 将作品数据转换为标准结果对象。
     *
     * @param array<string,mixed> $photo
     * @param string $url
     *
     * @return Result
     */
    protected function buildResult(array $photo, string $url): Result
    {
        $images = $this->extractImages($photo);
        $videos = $this->extractVideos($photo);
        $isImage = !empty($images) && empty($videos);

        $common = [
            'platform' => self::PLATFORM,
            'url' => $url,
            'title' => $this->extractTitle($photo),
            'desc' => (string) ($photo['caption'] ?? ''),
            'cover' => $this->extractCover($photo),
            'author' => $this->extractAuthor($photo),
            'raw' => $photo,
        ];

        if ($isImage) {
            return new ImageResult(array_merge($common, ['images' => $images]));
        }

        return new VideoResult(array_merge($common, ['videos' => $videos]));
    }

    /**
     * @param array<string,mixed> $photo
     *
     * @return string
     */
    protected function extractTitle(array $photo): string
    {
        $caption = (string) ($photo['caption'] ?? '');
        if ($caption === '') {
            return '';
        }

        $firstLine = preg_split('/\r\n|\r|\n/', $caption, 2)[0];

        return trim((string) $firstLine);
    }

    /**
     * @param array<string,mixed> $photo
     *
     * @return string
     */
    protected function extractCover(array $photo): string
    {
        // 新版移动端：coverUrls 为 [{url:...}] 数组
        foreach (['coverUrls', 'webpCoverUrls'] as $key) {
            $coverUrls = $photo[$key] ?? null;
            if (!is_array($coverUrls)) {
                continue;
            }
            foreach ($coverUrls as $cover) {
                if (is_array($cover) && isset($cover['url'])) {
                    return (string) $cover['url'];
                }
                if (is_string($cover) && $cover !== '') {
                    return $cover;
                }
            }
        }

        return (string) ($photo['coverUrl'] ?? '');
    }

    /**
     * @param array<string,mixed> $photo
     *
     * @return array<int,string>
     */
    protected function extractImages(array $photo): array
    {
        $atlas = $photo['atlas'] ?? $photo['ext_params']['atlas'] ?? null;
        if (!is_array($atlas)) {
            return [];
        }

        // 新版：list(路径) + cdn(域名) 拼接
        $list = $atlas['list'] ?? null;
        if (is_array($list)) {
            $host = $this->extractCdnHost($atlas);
            $images = [];
            foreach ($list as $path) {
                if (!is_string($path)) {
                    continue;
                }
                $path = str_replace('\\/', '/', $path);
                $images[] = $host !== '' ? 'https://' . $host . $path : $path;
            }
            if (!empty($images)) {
                return array_values(array_unique($images));
            }
        }

        // 旧版：cdn 数组为完整 URL，或 [{cdn,url}] 结构
        $cdnList = $atlas['cdn'] ?? $atlas['images'] ?? [];
        $list2 = [];
        if (is_array($cdnList)) {
            foreach ($cdnList as $item) {
                if (is_string($item) && $item !== '') {
                    $list2[] = $item;
                    continue;
                }
                if (is_array($item)) {
                    $url = (string) ($item['url'] ?? $item['cdn'] ?? '');
                    if ($url !== '') {
                        $list2[] = $url;
                    }
                }
            }
        }

        foreach (['cdn0', 'cdn1', 'cdn2', 'cdn3', 'cdn4'] as $key) {
            if (isset($atlas[$key]) && is_string($atlas[$key]) && $atlas[$key] !== '') {
                $list2[] = $atlas[$key];
            }
        }

        return array_values(array_unique($list2));
    }

    /**
     * 从 atlas 中提取首个 CDN 域名。
     *
     * @param array<string,mixed> $atlas
     *
     * @return string
     */
    protected function extractCdnHost(array $atlas): string
    {
        if (isset($atlas['cdn']) && is_array($atlas['cdn'])) {
            foreach ($atlas['cdn'] as $c) {
                if (is_string($c) && $c !== '') {
                    return $c;
                }
            }
        }

        if (isset($atlas['cdnList']) && is_array($atlas['cdnList'])) {
            foreach ($atlas['cdnList'] as $c) {
                if (is_array($c) && isset($c['cdn']) && is_string($c['cdn'])) {
                    return $c['cdn'];
                }
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $photo
     *
     * @return array<int,array<string,mixed>>
     */
    protected function extractVideos(array $photo): array
    {
        $videos = [];

        $mainMvUrls = $photo['mainMvUrls'] ?? null;
        if (is_array($mainMvUrls)) {
            foreach ($mainMvUrls as $mv) {
                if (!is_array($mv)) {
                    continue;
                }
                $url = (string) ($mv['url'] ?? '');
                if ($url === '' && isset($mv['host'])) {
                    $url = (string) $mv['host'];
                }
                if ($url !== '') {
                    $videos[] = [
                        'url' => $url,
                        'quality' => (string) ($mv['qualityType'] ?? 'normal'),
                        'format' => 'mp4',
                    ];
                }
            }
        }

        $photoUrl = $photo['photoUrl'] ?? null;
        if (is_string($photoUrl) && $photoUrl !== '' && !$this->containsUrl($videos, $photoUrl)) {
            $videos[] = [
                'url' => $photoUrl,
                'quality' => 'normal',
                'format' => 'mp4',
            ];
        }

        return $videos;
    }

    /**
     * @param array<string,mixed> $photo
     *
     * @return Author
     */
    protected function extractAuthor(array $photo): Author
    {
        $author = $photo['author'] ?? $photo['user'] ?? [];
        if (!is_array($author)) {
            $author = [];
        }

        $id = (string) ($author['eid'] ?? $author['id'] ?? $author['userId'] ?? $photo['kwaiId'] ?? $photo['userId'] ?? '');
        $name = (string) ($author['name'] ?? $author['nickname'] ?? $photo['userName'] ?? '');
        $avatar = (string) ($author['headerUrl'] ?? $author['avatar'] ?? $author['avatarUrl'] ?? $photo['headUrl'] ?? '');

        return new Author($id, $name, $avatar);
    }

    /**
     * @param array<int,array<string,mixed>> $videos
     * @param string $url
     *
     * @return bool
     */
    protected function containsUrl(array $videos, string $url): bool
    {
        foreach ($videos as $video) {
            if (isset($video['url']) && $video['url'] === $url) {
                return true;
            }
        }

        return false;
    }
}
