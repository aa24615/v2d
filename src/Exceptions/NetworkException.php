<?php

declare(strict_types=1);

namespace Zyan\V2d\Exceptions;

/**
 * 当网络请求失败（超时、HTTP 5xx 等）时抛出。
 *
 * @package Zyan\V2d\Exceptions
 */
class NetworkException extends Exception
{
}
