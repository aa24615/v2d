<?php

declare(strict_types=1);

namespace Zyan\V2d\Results;

/**
 * 视频类型结果，携带视频地址。
 *
 * @package Zyan\V2d\Results
 */
class VideoResult extends Result
{
    protected string $type = self::TYPE_VIDEO;

    /**
     * @var array<int,array<string,mixed>> 视频地址列表（多清晰度/格式）
     */
    protected array $videos = [];

    public function __construct(array $data = [])
    {
        parent::__construct($data);

        if (isset($data['videos']) && is_array($data['videos'])) {
            $this->videos = array_values(array_filter($data['videos'], 'is_array'));
        }
    }

    /**
     * 获取最优（首个）视频地址。
     *
     * @return string
     */
    public function getVideoUrl(): string
    {
        if (empty($this->videos)) {
            return '';
        }

        $first = reset($this->videos);

        return (string) ($first['url'] ?? '');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getVideos(): array
    {
        return $this->videos;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'videos' => $this->videos,
        ]);
    }
}
