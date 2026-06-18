<?php

declare(strict_types=1);

namespace Zyan\V2d;

use GuzzleHttp\ClientInterface;
use Zyan\V2d\Adapters\Douyin\DouyinAdapter;
use Zyan\V2d\Adapters\Kuaishou\KuaishouAdapter;
use Zyan\V2d\Adapters\Xiaohongshu\XiaohongshuAdapter;
use Zyan\V2d\Contracts\AdapterInterface;
use Zyan\V2d\Exceptions\InvalidUrlException;
use Zyan\V2d\Results\Result;

/**
 * V2d SDK 主入口。
 *
 * 负责注册平台适配器、根据分享链接自动识别平台并完成抓取。
 *
 * @package Zyan\V2d
 */
class V2d
{
    /**
     * @var array<int,AdapterInterface>
     */
    protected array $adapters = [];

    /**
     * @param AdapterInterface[]|null $adapters 自定义适配器集合，为空则注册内置适配器
     * @param ClientInterface|null    $client   可注入的 HTTP 客户端
     */
    public function __construct(?array $adapters = null, ?ClientInterface $client = null)
    {
        if ($adapters === null) {
            $adapters = [
                new DouyinAdapter($client),
                new KuaishouAdapter($client),
                new XiaohongshuAdapter($client),
            ];
        }

        foreach ($adapters as $adapter) {
            $this->register($adapter);
        }
    }

    /**
     * 注册一个适配器。
     *
     * @param AdapterInterface $adapter
     *
     * @return $this
     */
    public function register(AdapterInterface $adapter): self
    {
        $this->adapters[] = $adapter;

        return $this;
    }

    /**
     * 获取所有已注册的适配器。
     *
     * @return array<int,AdapterInterface>
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * 根据分享链接找到支持的适配器。
     *
     * @param string $url
     *
     * @return AdapterInterface
     *
     * @throws InvalidUrlException
     */
    public function resolve(string $url): AdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($url)) {
                return $adapter;
            }
        }

        throw new InvalidUrlException(sprintf('未找到能处理该链接的适配器: %s', $url));
    }

    /**
     * 抓取并解析分享链接，返回标准结果对象。
     *
     * @param string $url 分享链接（短链或长链）
     *
     * @return Result
     */
    public function fetch(string $url): Result
    {
        return $this->resolve($url)->fetch($url);
    }

    /**
     * 抓取并以数组形式返回结果。
     *
     * @param string $url
     *
     * @return array<string,mixed>
     */
    public function fetchArray(string $url): array
    {
        return $this->fetch($url)->toArray();
    }

    /**
     * 抓取并以 JSON 字符串形式返回结果。
     *
     * @param string $url
     * @param int    $options json_encode 选项
     *
     * @return string
     */
    public function fetchJson(string $url, int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return $this->fetch($url)->toJson($options);
    }
}
