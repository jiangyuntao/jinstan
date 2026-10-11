# Foundation Application 与 Service Provider

## 当前状态

1.0 的 Foundation 基础设施包括：

- `Jinstan\Core\Foundation\Application`
- `Jinstan\Core\Providers\ServiceProvider`
- `Jinstan\Core\Providers\FoundationServiceProvider`

`Foundation\Application` 管理服务生命周期；`Http\Application` 是将 PSR-7 Request 交给 HTTP Kernel 的窄入口。

## Application 职责

Foundation Application 当前负责：

- 持有项目根目录。
- 持有运行环境。
- 持有 Core Container。
- 注册 Service Provider。
- 在所有 Provider 注册后执行 boot 生命周期。
- 防止启动后继续注册 Provider。

Application 只允许 Provider 和 Composition Root 访问 Container。Controller、Domain、View 和普通 Service 不得把它当作 Service Locator。

## Provider 生命周期

每个 Provider 实现：

```php
interface ServiceProvider
{
    public function register(Application $application): void;

    public function boot(Application $application): void;
}
```

约束：

- `register()` 只绑定配置和服务。
- `boot()` 在全部 Provider 完成注册后执行，用于 Route、Event、Component 等需要完整依赖图的注册行为。
- 同一 Provider Class 重复注册时只执行一次。
- Application 重复 boot 时只执行一次。
- Application boot 后不允许加入新 Provider。

## FoundationServiceProvider

负责：

- 合并 `config/app.php` 与 `config/database.php`。
- 注册 `Config`。
- 创建 Core Logger。
- 同时绑定具体 `Monolog\Logger` 和 `Psr\Log\LoggerInterface`。
- 注册 Router、Route Registrar、URL Generator 与 Response Factory。
- 注册 Event、Translation、CSRF 与 Maintenance Mode 的公共契约和实现。

后续 Provider 应依赖 `LoggerInterface`，避免业务代码绑定 Monolog 实现。

## 运行环境

运行环境通过 `APP_ENV` 设置，默认值为 `production`：

```text
APP_ENV=local
APP_ENV=testing
APP_ENV=production
```

`APP_DEBUG` 仍独立控制 HTTP 错误详情。不能因为环境为 local 就绕过 Authentication、Permission 或 CSRF。

## Provider 分工

Provider 拆分、HTTP Kernel、Middleware Pipeline 与 Exception Handler 均已完成。Bootstrap 不再直接构造 Controller、Repository 或具体 Route。

`DatabaseServiceProvider` 与 Content 数据层 Provider 已完成，具体边界见：

- `docs/database/database-service-provider.md`
- `docs/content-types/content-service-provider.md`
- `docs/themes/theme-service-provider.md`
- `docs/configuration/admin-service-provider.md`
- `docs/plugins/plugin-service-provider.md`
- `docs/installation/installer-service-provider.md`
- `docs/routing/route-service-provider.md`

完整路由快照用于防止 Provider 顺序和动态 Content Route 优先级漂移。
