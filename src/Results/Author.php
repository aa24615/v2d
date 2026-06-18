<?php

declare(strict_types=1);

namespace Zyan\V2d\Results;

/**
 * 作者信息值对象。
 *
 * @package Zyan\V2d\Results
 */
class Author
{
    protected string $id = '';
    protected string $nickname = '';
    protected string $avatar = '';

    public function __construct(string $id = '', string $nickname = '', string $avatar = '')
    {
        $this->id = $id;
        $this->nickname = $nickname;
        $this->avatar = $avatar;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
        ];
    }
}
