<?php

declare(strict_types=1);

namespace Zyan\V2d\Results;

/**
 * 图文类型结果，携带图片列表。
 *
 * @package Zyan\V2d\Results
 */
class ImageResult extends Result
{
    protected string $type = self::TYPE_IMAGE;

    /**
     * @var array<int,string> 图片地址列表
     */
    protected array $images = [];

    public function __construct(array $data = [])
    {
        parent::__construct($data);

        if (isset($data['images']) && is_array($data['images'])) {
            $this->images = array_values(array_filter($data['images'], 'is_string'));
        }
    }

    /**
     * @return array<int,string>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'images' => $this->images,
        ]);
    }
}
