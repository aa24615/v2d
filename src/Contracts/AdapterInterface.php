<?php

declare(strict_types=1);

namespace Zyan\V2d\Contracts;

use Zyan\V2d\Results\Result;

/**
 * 各平台抓取适配器的统一契约。
 *
 * @package Zyan\V2d\Contracts
 */
interface AdapterInterface
{
    /**
     * 判断适配器是否能处理给定的分享链接。
     *
     * @param string $url 分享链接（短链或长链）
     *
     * @return bool
     */
    public function supports(string $url): bool;

    /**
     * 抓取并解析分享链接，返回标准结果对象。
     *
     * @param string $url 分享链接（短链或长链）
     *
     * @return Result
     */
    public function fetch(string $url): Result;

    /**
     * 获取适配器对应的平台标识。
     *
     * @return string
     */
    public function getPlatform(): string;
}
