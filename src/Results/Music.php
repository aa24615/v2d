<?php

declare(strict_types=1);

namespace Zyan\V2d\Results;

/**
 * 音乐（背景音乐）信息值对象。
 *
 * @package Zyan\V2d\Results
 */
class Music
{
    protected string $id = '';
    protected string $title = '';
    protected string $author = '';
    protected string $url = '';
    protected string $cover = '';

    public function __construct(
        string $id = '',
        string $title = '',
        string $author = '',
        string $url = '',
        string $cover = ''
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->author = $author;
        $this->url = $url;
        $this->cover = $cover;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * 音乐播放地址（部分平台因版权可能为空）。
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCover(): string
    {
        return $this->cover;
    }

    /**
     * 是否存在有效的音乐信息。
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->id === '' && $this->title === '' && $this->url === '';
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'url' => $this->url,
            'cover' => $this->cover,
        ];
    }
}
