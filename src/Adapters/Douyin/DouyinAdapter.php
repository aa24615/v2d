<?php

declare(strict_types=1);

namespace Zyan\V2d\Adapters\Douyin;

use Zyan\V2d\Adapters\AbstractAdapter;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Exceptions\ParseException;
use Zyan\V2d\Results\Author;
use Zyan\V2d\Results\ImageResult;
use Zyan\V2d\Results\Result;
use Zyan\V2d\Results\VideoResult;

/**
 * 抖音（Douyin）抓取适配器。
 *
 * 支持以下分享链接形式：
 *  - 短链: https://v.douyin.com/xxxxx/
 *  - 长链: https://www.douyin.com/video/{aweme_id}
 *  - 旧版: https://www.iesdouyin.com/share/video/{aweme_id}/
 *
 * 通过 iesdouyin 分享页面中的 _ROUTER_DATA 结构解析图文与视频。
 *
 * @package Zyan\V2d\Adapters\Douyin
 */
class DouyinAdapter extends AbstractAdapter
{
    public const PLATFORM = 'douyin';

    /**
     * 图集类型 aweme_type 集合（抖音内部用于标识图集）。
     */
    protected const IMAGE_TYPES = [68, 150, 151, 152, 169];

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

        return (bool) preg_match('#v\.douyin\.com/#i', $url)
            || (bool) preg_match('#douyin\.com/(?:video|note|share/video)/#i', $url)
            || (bool) preg_match('#iesdouyin\.com/share/video/#i', $url);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(string $url): Result
    {
        $url = trim($url);
        if (!$this->supports($url)) {
            throw new InvalidUrlException(sprintf('无法识别的抖音分享链接: %s', $url));
        }

        $awemeId = $this->extractAwemeId($url);

        // 优先使用 iesdouyin 分享页面（结构稳定、对未登录友好）
        $shareUrl = $awemeId !== ''
            ? sprintf('https://www.iesdouyin.com/share/video/%s/', $awemeId)
            : $url;

        [$body, $finalUrl] = $this->httpGet($shareUrl);

        // 落地链接可能携带真正的 aweme_id（短链重定向后）
        if ($awemeId === '') {
            $awemeId = $this->extractAwemeId($finalUrl);
        }

        $item = $this->extractItem($body);

        return $this->buildResult($item, $finalUrl ?: $shareUrl);
    }

    /**
     * 从链接或落地链接中提取 aweme_id。
     *
     * @param string $url
     *
     * @return string
     */
    protected function extractAwemeId(string $url): string
    {
        if (preg_match('#/(?:video|note|share/video)/(\d+)#i', $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * 从页面 HTML 中解析出作品原始数据。
     *
     * @param string $html
     *
     * @return array<string,mixed>
     *
     * @throws ParseException
     */
    protected function extractItem(string $html): array
    {
        // 新版分享页：window._ROUTER_DATA = {...};
        $data = $this->matchJson($html, '/window\._ROUTER_DATA\s*=\s*(\{.*?\})\s*;?\s*<\/script>/s');

        if (is_array($data)) {
            $item = $this->findItemInRouterData($data);
            if (!empty($item)) {
                return $item;
            }
        }

        // 兜底：旧版 itemInfo JSON
        $data = $this->matchJson($html, '/window\._ROUTER_DATA\s*=\s*(\{.*\})\s*;?\s*$/m');
        if (is_array($data)) {
            $item = $this->findItemInRouterData($data);
            if (!empty($item)) {
                return $item;
            }
        }

        throw new ParseException('无法从抖音页面解析出作品数据，可能页面结构已变更或被风控。');
    }

    /**
     * @param array<string,mixed> $routerData
     *
     * @return array<string,mixed>
     */
    protected function findItemInRouterData(array $routerData): array
    {
        $loaderData = $routerData['loaderData'] ?? $routerData;
        if (!is_array($loaderData)) {
            return [];
        }

        foreach ($loaderData as $page) {
            if (!is_array($page)) {
                continue;
            }

            $videoInfo = $page['videoInfoRes'] ?? $page['videoInfo'] ?? null;
            if (is_array($videoInfo)) {
                $itemList = $videoInfo['item_list'] ?? $videoInfo['items'] ?? null;
                if (is_array($itemList) && !empty($itemList) && isset($itemList[0]) && is_array($itemList[0])) {
                    return $itemList[0];
                }
            }
        }

        return [];
    }

    /**
     * 将原始 item 转换为标准结果对象。
     *
     * @param array<string,mixed> $item
     * @param string $url
     *
     * @return Result
     */
    protected function buildResult(array $item, string $url): Result
    {
        $awemeType = (int) ($item['aweme_type'] ?? 0);
        $images = $this->extractImages($item);
        $isImage = in_array($awemeType, self::IMAGE_TYPES, true) || !empty($images);

        $common = [
            'platform' => self::PLATFORM,
            'url' => $url,
            'title' => $this->extractTitle($item),
            'desc' => (string) ($item['desc'] ?? ''),
            'cover' => $this->extractCover($item),
            'author' => $this->extractAuthor($item),
            'raw' => $item,
        ];

        if ($isImage) {
            return new ImageResult(array_merge($common, ['images' => $images]));
        }

        $videos = $this->extractVideos($item);

        return new VideoResult(array_merge($common, ['videos' => $videos]));
    }

    /**
     * @param array<string,mixed> $item
     *
     * @return string
     */
    protected function extractTitle(array $item): string
    {
        $desc = (string) ($item['desc'] ?? '');
        if ($desc === '') {
            return '';
        }

        // 截取第一行作为标题
        $firstLine = preg_split('/\r\n|\r|\n/', $desc, 2)[0];

        return trim((string) $firstLine);
    }

    /**
     * @param array<string,mixed> $item
     *
     * @return string
     */
    protected function extractCover(array $item): string
    {
        // 视频封面
        $video = $item['video'] ?? null;
        if (is_array($video)) {
            $cover = $video['cover'] ?? $video['origin_cover'] ?? null;
            if (is_array($cover)) {
                return $this->pickUrl($cover['url_list'] ?? []);
            }
        }

        // 图集首张作为封面
        $images = $this->extractImages($item);

        return !empty($images) ? $images[0] : '';
    }

    /**
     * @param array<string,mixed> $item
     *
     * @return array<int,string>
     */
    protected function extractImages(array $item): array
    {
        $images = $item['images'] ?? $item['image_list'] ?? null;
        if (!is_array($images)) {
            return [];
        }

        $list = [];
        foreach ($images as $image) {
            if (!is_array($image)) {
                continue;
            }

            $url = $this->pickUrl($image['url_list'] ?? $image['url'] ?? []);
            if ($url === '') {
                $url = (string) ($image['url_default'] ?? '');
            }
            // 替换为更高清的原图域名
            if ($url !== '') {
                $list[] = $this->toHd($url);
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @param array<string,mixed> $item
     *
     * @return array<int,array<string,mixed>>
     */
    protected function extractVideos(array $item): array
    {
        $video = $item['video'] ?? null;
        if (!is_array($video)) {
            return [];
        }

        $videos = [];

        // play_addr 通常是无水印地址
        $playAddr = $video['play_addr'] ?? $video['play_addr_lowbr'] ?? null;
        if (is_array($playAddr)) {
            $url = $this->pickUrl($playAddr['url_list'] ?? []);
            if ($url !== '') {
                $videos[] = [
                    'url' => $this->fixVideoUrl($url),
                    'quality' => 'normal',
                    'format' => 'mp4',
                ];
            }
        }

        // play_addr_h264
        $h264 = $video['play_addr_h264'] ?? null;
        if (is_array($h264)) {
            $url = $this->pickUrl($h264['url_list'] ?? []);
            if ($url !== '') {
                $videos[] = [
                    'url' => $this->fixVideoUrl($url),
                    'quality' => 'h264',
                    'format' => 'mp4',
                ];
            }
        }

        return $videos;
    }

    /**
     * @param array<string,mixed> $item
     *
     * @return Author
     */
    protected function extractAuthor(array $item): Author
    {
        $author = $item['author'] ?? [];
        if (!is_array($author)) {
            $author = [];
        }

        $avatar = $author['avatar_thumb'] ?? $author['avatar_medium'] ?? [];
        $avatarUrls = is_array($avatar) ? ($avatar['url_list'] ?? []) : [];

        return new Author(
            (string) ($author['uid'] ?? $author['short_id'] ?? ''),
            (string) ($author['nickname'] ?? ''),
            $this->pickUrl($avatarUrls)
        );
    }

    /**
     * 从 url_list 中挑选首个有效地址。
     *
     * @param mixed $urlList
     *
     * @return string
     */
    protected function pickUrl($urlList): string
    {
        if (is_string($urlList) && $urlList !== '') {
            return $urlList;
        }

        if (!is_array($urlList)) {
            return '';
        }

        foreach ($urlList as $url) {
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * 将抖音图片地址替换为更高清的版本。
     *
     * @param string $url
     *
     * @return string
     */
    protected function toHd(string $url): string
    {
        return preg_replace(
            '/~[\w-]+\.(\w+)(\?|$)/',
            '~tplv-origin.$1$2',
            $url
        ) ?? $url;
    }

    /**
     * 修正视频地址（部分接口返回 302 临时域名，统一为可访问形式）。
     *
     * @param string $url
     *
     * @return string
     */
    protected function fixVideoUrl(string $url): string
    {
        return str_replace('playwm', 'play', $url);
    }
}
