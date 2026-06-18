<?php

declare(strict_types=1);

namespace Zyan\V2d\Results;

/**
 * 抓取结果基类，统一图文与视频结果的公共字段。
 *
 * @package Zyan\V2d\Results
 */
abstract class Result
{
    /**
     * 内容类型：图文。
     */
    public const TYPE_IMAGE = 'image';

    /**
     * 内容类型：视频。
     */
    public const TYPE_VIDEO = 'video';

    protected string $type;
    protected string $platform;
    protected string $url;
    protected string $title = '';
    protected string $desc = '';
    protected Author $author;
    protected Music $music;
    protected string $cover = '';
    protected array $raw = [];

    public function __construct(array $data = [])
    {
        $this->author = new Author();
        $this->music = new Music();

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                if ($key === 'author' && is_array($value)) {
                    $this->author = new Author(
                        $value['id'] ?? '',
                        $value['nickname'] ?? '',
                        $value['avatar'] ?? ''
                    );
                    continue;
                }
                if ($key === 'music' && is_array($value)) {
                    $this->music = new Music(
                        (string) ($value['id'] ?? ''),
                        (string) ($value['title'] ?? ''),
                        (string) ($value['author'] ?? ''),
                        (string) ($value['url'] ?? ''),
                        (string) ($value['cover'] ?? '')
                    );
                    continue;
                }
                $this->{$key} = $value;
            }
        }

        if (isset($data['author']) && $data['author'] instanceof Author) {
            $this->author = $data['author'];
        }

        if (isset($data['music']) && $data['music'] instanceof Music) {
            $this->music = $data['music'];
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDesc(): string
    {
        return $this->desc;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }

    /**
     * 获取背景音乐信息（无音乐时返回空的 Music 对象）。
     *
     * @return Music
     */
    public function getMusic(): Music
    {
        return $this->music;
    }

    public function getCover(): string
    {
        return $this->cover;
    }

    /**
     * @return array<string,mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * 序列化为标准数组。
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'platform' => $this->platform,
            'url' => $this->url,
            'title' => $this->title,
            'desc' => $this->desc,
            'author' => $this->author->toArray(),
            'music' => $this->music->toArray(),
            'cover' => $this->cover,
            'raw' => $this->raw,
        ];
    }

    /**
     * 序列化为 JSON 字符串。
     *
     * @param int $options json_encode 选项
     *
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $options);
    }
}
