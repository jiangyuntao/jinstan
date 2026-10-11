# HTTP Response API

## 获取 Response Factory

Core 内部服务通过构造器注入 `Jinstan\Core\Http\ResponseFactory`。该服务由 Foundation Provider 注册，Plugin 不应自行实例化第三方 PSR-7 Factory 并暴露为公共 API。

## 基本用法

```php
use Jinstan\Core\Http\ResponseFactory;

final class ExampleController
{
    public function __construct(private readonly ResponseFactory $responses)
    {
    }

    public function show(): ResponseInterface
    {
        return $this->responses->html('<h1>Example</h1>');
    }

    public function store(): ResponseInterface
    {
        return $this->responses->redirect('/admin/examples');
    }
}
```

JSON 响应只接受数组，并统一输出 UTF-8 JSON Content-Type。文件响应要求目标是可读普通文件；文件路径必须由调用方在进入 Factory 前完成权限和目录边界验证。

当前已迁移的 Controller：

- `Auth\LoginController`
- `Admin\LanguageAdminController`
- `Admin\PluginAdminController`
- `Admin\CacheLogsController`
- `Admin\RoleAdminController`
- `Admin\DatabaseToolsController`
- `Admin\ThemeAdminController`
- `Admin\NavigationAdminController`
- `Admin\TaxonomyAdminController`
- `Admin\ContentTypeAdminController`
- `Admin\ContentAdminController`
- `Admin\UserAdminController`
- `Admin\SettingsAdminController`
- `Admin\DashboardController`
- `Admin\MediaAdminController`

所有 Controller 都显式注入同一个 Response Factory。Factory 的文件读取器只在使用 `file()` 时必需；缺少读取器会抛出 LogicException，而不会绕过路径边界。

## 标准异常

Controller 或 Middleware 可以抛出 Core 的 HTTP 异常，由 Kernel 转换为 Response。HTML 请求获得状态码和安全消息；声明接受 JSON 的请求获得：

```json
{
  "message": "Validation Failed",
  "errors": {
    "title": ["The title is required."]
  }
}
```

未知异常不得作为业务控制流使用。它们会被记录，并在生产环境转换为不包含内部细节的 500 响应。

Plugin 可通过 `PluginRegistrar::responses()` 获取同一个响应工厂。Plugin 路由回调返回 PSR `ResponseInterface`，不得将 Nyholm 或其他具体 PSR-7 实现作为 Plugin 公共 API 类型。

## 后台安全响应

所有 `/admin` 请求先经过后台安全 Middleware：

- 未登录的普通浏览器请求返回 303 并跳转到 `/login`。
- 未登录且声明接受 JSON 的请求返回 401 JSON。
- POST、PUT、PATCH、DELETE 等非只读请求必须提供有效 CSRF Token，否则返回 419。

后台认证、CSRF 和权限拒绝由路由 Middleware 统一转换为跳转、419 或 403 响应，Controller 只保留资源级业务保护。
