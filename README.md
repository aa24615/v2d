# Zyan V2d

> 一个标准的短视频 APP 视频 / 图文抓取 SDK（Packagist 包），支持抖音、小红书、快手等平台，返回统一标准格式。

[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## 简介

`zyan/v2d` 用于从短视频平台的「分享链接」中抓取并解析出作品的标题、作者、封面、图片列表或视频地址，并统一为标准结构返回，便于做二次开发、内容归档、数据分析等。

- 统一入口：传入分享链接，自动识别平台并抓取
- 统一格式：图文与视频分别返回 `ImageResult` / `VideoResult`
- 可扩展：基于 `AdapterInterface` 可轻松接入新平台
- 可定制：支持注入 Cookie、自定义请求头、HTTP 客户端与代理，应对风控
- PHP 7.4+ 友好，零侵入依赖（Guzzle + Symfony DomCrawler）

## 支持平台

| 平台 | 标识 | 图文 | 视频 | 备注 |
| --- | --- | :---: | :---: | --- |
| 抖音 (Douyin) | `douyin` | ✅ | ✅ | 通过 iesdouyin 分享页 `_ROUTER_DATA` 解析 |
| 快手 (Kuaishou) | `kuaishou` | ✅ | ✅ | 兼容移动端 `v.m.chenzhongtech.com` 与 `__APOLLO_STATE__` |
| 小红书 (Xiaohongshu) | `xiaohongshu` | ✅ | ✅ | 服务器端易触发风控，需配置 Cookie（见 [常见问题](#常见问题)） |

## 环境要求

- PHP >= 7.4
- PHP 扩展：`json`、`mbstring`、`curl`（Guzzle 依赖）

## 安装

```bash
composer require zyan/v2d
```

## 快速开始

```php
use Zyan\V2d\V2d;

$v2d = new V2d();

// 直接传入分享短链，自动识别平台
$result = $v2d->fetch('https://v.douyin.com/coTA27_qFBA/');

// 内容类型：image 或 video
echo $result->getType();      // image
echo $result->getPlatform();  // douyin
echo $result->getTitle();     // 标题
echo $result->getAuthor()->getNickname(); // 作者昵称

if ($result->getType() === 'image') {
    foreach ($result->getImages() as $image) {
        echo $image . PHP_EOL;
    }
} else {
    echo $result->getVideoUrl(); // 最优视频地址
}
```

也可以直接获取数组或 JSON：

```php
$array = $v2d->fetchArray('https://v.kuaishou.com/Jdl248ZA');
$json  = $v2d->fetchJson('http://xhslink.com/o/wjWTM5ZvMj');
```

## 标准返回格式

### 图文 `ImageResult`

```json
{
    "type": "image",
    "platform": "douyin",
    "url": "https://www.iesdouyin.com/share/video/7234567890123/",
    "title": "图文标题",
    "desc": "完整描述",
    "author": {
        "id": "user_id",
        "nickname": "作者昵称",
        "avatar": "https://example.com/avatar.jpg"
    },
    "cover": "https://example.com/cover.jpg",
    "images": [
        "https://example.com/img1.jpg",
        "https://example.com/img2.jpg"
    ],
    "raw": { }
}
```

### 视频 `VideoResult`

```json
{
    "type": "video",
    "platform": "kuaishou",
    "url": "https://v.m.chenzhongtech.com/fw/photo/3x1234567890",
    "title": "视频标题",
    "desc": "完整描述",
    "author": {
        "id": "kwai_id",
        "nickname": "作者昵称",
        "avatar": "https://example.com/head.jpg"
    },
    "cover": "https://example.com/cover.jpg",
    "videos": [
        { "url": "https://example.com/video.mp4", "quality": "normal", "format": "mp4" }
    ],
    "raw": { }
}
```

### 结果对象 API

| 方法 | 说明 |
| --- | --- |
| `getType()` | 内容类型：`image` / `video` |
| `getPlatform()` | 平台标识 |
| `getUrl()` | 最终落地链接 |
| `getTitle()` | 标题（描述的首行） |
| `getDesc()` | 完整描述 |
| `getAuthor()` | 作者对象（`getId` / `getNickname` / `getAvatar`） |
| `getCover()` | 封面地址 |
| `getRaw()` | 平台原始数据 |
| `toArray()` / `toJson()` | 序列化输出 |
| `ImageResult::getImages()` | 图片地址列表 |
| `VideoResult::getVideos()` | 视频地址列表 |
| `VideoResult::getVideoUrl()` | 最优（首个）视频地址 |

## 单独使用适配器

如果只想针对单个平台，可直接使用对应适配器：

```php
use Zyan\V2d\Adapters\Douyin\DouyinAdapter;
use Zyan\V2d\Adapters\Kuaishou\KuaishouAdapter;
use Zyan\V2d\Adapters\Xiaohongshu\XiaohongshuAdapter;

$douyin = new DouyinAdapter();
$result = $douyin->fetch('https://v.douyin.com/coTA27_qFBA/');

// 判断适配器是否支持某链接
$douyin->supports('https://v.douyin.com/xxx/'); // true
```

## 高级配置

### 注入 Cookie 绕过风控（小红书）

小红书对服务器端 IP 风控较严，直接抓取可能被重定向到安全校验页。可从浏览器中复制对应站点的 Cookie 注入：

```php
use Zyan\V2d\Adapters\Xiaohongshu\XiaohongshuAdapter;

$adapter = (new XiaohongshuAdapter())
    ->withCookie('webId=xxx; a1=xxx; web_session=xxx;');

$result = $adapter->fetch('http://xhslink.com/o/wjWTM5ZvMj');
```

> 获取方式：浏览器打开小红书笔记页 → F12 → Network → 任一请求 → 复制 `Cookie` 请求头。

### 自定义请求头

```php
$adapter->withHeaders([
    'User-Agent' => '你的 UA',
    'Referer'    => 'https://www.xiaohongshu.com/',
    'Cookie'     => '...',
]);
// 或整体替换
$adapter->setHeaders(['User-Agent' => '...']);
```

### 使用代理 / 自定义 HTTP 客户端

可注入任意 `GuzzleHttp\ClientInterface` 实现，配合代理、超时等：

```php
use GuzzleHttp\Client;
use Zyan\V2d\V2d;

$client = new Client([
    'proxy' => 'http://127.0.0.1:7890',
    'timeout' => 20,
    'verify' => false,
]);

$v2d = new V2d(null, $client);
$result = $v2d->fetch('https://v.kuaishou.com/Jdl248ZA');
```

### 自定义适配器

实现 `AdapterInterface` 即可接入新平台：

```php
use Zyan\V2d\Adapters\AbstractAdapter;
use Zyan\V2d\Results\Result;

class BilibiliAdapter extends AbstractAdapter
{
    public function getPlatform(): string
    {
        return 'bilibili';
    }

    public function supports(string $url): bool
    {
        return (bool) preg_match('#b23\.tv/#i', $url);
    }

    public function fetch(string $url): Result
    {
        // 解析逻辑...
    }
}

$v2d = new V2d();
$v2d->register(new BilibiliAdapter());
```

## 异常处理

所有异常均继承自 `Zyan\V2d\Exceptions\Exception`：

| 异常 | 触发场景 |
| --- | --- |
| `InvalidUrlException` | 链接无法被任何适配器识别 |
| `NetworkException` | 网络请求失败或返回 4xx/5xx |
| `ParseException` | 页面结构变更或被风控，无法解析数据 |

```php
use Zyan\V2d\V2d;
use Zyan\V2d\Exceptions\Exception;

try {
    $result = (new V2d())->fetch($url);
} catch (Exception $e) {
    // 统一捕获
    echo $e->getMessage();
}
```

## 测试

```bash
composer install
composer test
```

测试使用 Mock HTTP 客户端注入模拟页面，覆盖各平台图文 / 视频解析逻辑，不依赖网络，运行稳定。

## 常见问题

**Q：小红书抓取报「触发了风控校验」？**
A：小红书对服务器端 IP 有严格风控，会重定向到安全校验 404 页。请通过 `withCookie()` 注入浏览器中真实的 Cookie，或使用代理 IP。

**Q：抓取到的图片是高清图吗？**
A：抖音图集会尝试替换为原图参数；快手返回的即为平台 CDN 图集地址；如需更高清，可对返回 URL 自行调整参数。

**Q：链接失效或被删除？**
A：会抛出 `ParseException`，建议捕获后跳过或重试。

**Q：平台页面结构变更导致解析失败？**
A：可在 Issue 中反馈，`raw` 字段保留了平台原始数据便于排查。

## 项目结构

```
src/
├── V2d.php                         # 主入口
├── Contracts/
│   └── AdapterInterface.php        # 适配器契约
├── Adapters/
│   ├── AbstractAdapter.php         # 适配器基类（HTTP/JSON 工具）
│   ├── Douyin/DouyinAdapter.php
│   ├── Kuaishou/KuaishouAdapter.php
│   └── Xiaohongshu/XiaohongshuAdapter.php
├── Results/
│   ├── Result.php                  # 结果基类
│   ├── Author.php                  # 作者值对象
│   ├── ImageResult.php             # 图文结果
│   └── VideoResult.php             # 视频结果
└── Exceptions/
    └── Exception.php               # 异常体系
tests/                              # PHPUnit 测试
```

## 贡献

欢迎通过 PR 接入更多平台或优化解析逻辑。请确保：

1. 新增 / 修改逻辑附带对应的 PHPUnit 测试
2. 遵循现有代码风格（PHP 7.4 兼容、强类型声明）
3. `composer test` 通过

## License

MIT License (c) zyan
