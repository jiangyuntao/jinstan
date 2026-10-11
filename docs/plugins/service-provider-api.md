# Plugin Service Provider API

新 Plugin 应实现 `Jinstan\Core\Plugin\PluginServiceProvider`。该接口组合安装生命周期与现代注册入口：

```php
final class Plugin implements PluginServiceProvider
{
    public function register(PluginRegistrar $registrar): void
    {
        $service = new ExampleService();
        $registrar->services()->register($service, ExampleServiceContract::class);
        $registrar->services()->register(new ExampleController($service, $registrar->responses()));
        $registrar->routes()->load(__DIR__ . '/routes/web.php');
    }

    public function boot(): void
    {
    }
}
```

`register()` 只登记服务和扩展定义，不应访问请求状态、发送响应或修改数据库 Schema。`boot()` 在全部 Core Provider 注册完成、且插件已安装并启用后执行，可完成依赖所有已注册扩展的初始化。Schema 只能通过 Plugin Migration 生命周期修改。

## Public API

`PluginRegistrar` 当前提供以下受控能力：

- `routes()`：`RouteRegistrar`，注册或加载 Plugin Route。
- `components()`：`ComponentRegistrar`，注册 Component，不暴露 Registry 查询和内部状态。
- `contentTypes()`：`ContentTypeRegistrar`，注册 Content Type，不暴露 Registry 查询能力。
- `services()`：`PluginServiceRegistrar`，注册 Plugin 自有 Service 及其实现的接口，不暴露 Container 查询能力。
- `fieldTypes()`：`FieldTypeRegistrar`，注册自定义 Field Type 的持久化标识、实现类和创建工厂。
- `events()`：`EventListenerRegistrar`，按对象事件类或接口注册同步 Listener，不提供事件分发能力。
- `translations()`：`TranslationRegistrar`，注册具名语言包目录，不暴露其他命名空间或缓存状态。
- `views()`：`ViewRegistrar`，注册具名 `.jst` View 目录，不提供命名空间查询或解析能力。
- `viewRenderer()`：`ViewRenderer`，渲染已注册的 View，不暴露 Latte Engine 和 Loader。
- `contents()`：`ContentReadRepository`，只读 Content 能力。
- `settings()`：`SettingsReadRepository`，只读设置能力。
- `csrf()`：返回 `CsrfTokenProvider`，仅提供表单 Token 生成与验证。
- `responses()`：PSR-7 `ResponseFactory`。

以上接口是 Plugin 可依赖的 Public API。具体 Repository、数据库 Connection、Container、ComponentRegistry、ContentTypeRegistry 和 Router 内部集合不是 Public API。Route 级 Middleware 通过 `Route::middleware()` 或 `RouteRegistrar::group()` 注册，不需要访问全局 HTTP Kernel。

### Service 注册约束

`services()->register($service, ...$contracts)` 始终按对象的具体类注册，也可以同时绑定一个或多个由该对象实现的接口。注册过程会先完成全部校验再写入，因此契约错误不会留下部分注册状态。

Plugin Service 必须使用 Plugin 自有的具名类，不得使用匿名类或 `Jinstan\Core\` 具体类；不得覆盖已经存在的 Core 或其他 Plugin Service。注册器是只写边界，Plugin 无法借此枚举或读取 Container。需要供 Route 使用的 Controller 也应显式注册，然后使用 `[ExampleController::class, 'method']` 作为 Route Action。

### Field Type 注册约束

自定义 Field Type 必须实现 `FieldType`，并提供稳定的持久化 handle 和根据字段设置重建实例的 factory：

```php
$registrar->fieldTypes()->register(
    'rating',
    RatingField::class,
    static fn (array $settings): FieldType => new RatingField((int) ($settings['maximum'] ?? 5)),
);
```

handle 会写入 Content Type 配置；后台重新加载配置时使用同一 factory 恢复 Field Type。因此插件升级时不得随意修改已发布的 handle。handle 和实现类都不能与现有类型重复，factory 必须返回声明的实现类。自定义 Field 仍须提供测试、文档、最小使用示例和可由 Admin Renderer 解析的 `.jst` View。

### Event Listener 注册约束

事件使用具名对象，Listener 按事件类或其接口注册。当前 Content 事件及同步执行语义见 [`docs/events/content-events.md`](../events/content-events.md)。Plugin 只获得 Listener 注册入口，不能主动 dispatch Core 事件。Listener 异常会继续向上传播，不会被静默记录后忽略。

### Translation 注册约束

默认后台语言包放在 Plugin 的 `lang/admin/<locale>.php`，由 Plugin Manager 按 Manifest name 自动注册。模板和 PHP 均使用 `plugin-name::key`，不会覆盖无命名空间的 Core 文案。目录约定、fallback 和参数替换见 [`docs/plugins/translations.md`](translations.md)。

### View 注册与渲染约束

默认 View 放在 Plugin 的 `views/`，由 Plugin Manager 按 Manifest name 自动注册。模板使用 `plugin-name::relative/path.jst` 完整名称；额外目录可以通过 `views()` 注册，渲染通过 `viewRenderer()` 完成。目录边界、路径安全和 Latte 转义要求见 [`docs/plugins/views.md`](views.md)。

官方 HelloPlugin 与 ExternalOrderPlugin 均使用这套 API。Plugin Manager 只接受 `PluginServiceProvider`，不再提供 `Plugin` 或 `PluginContext` 旧入口。ExternalOrderPlugin 使用 ComponentRegistrar、ContentReadRepository、SettingsReadRepository、CSRF 与 ResponseFactory，不依赖具体 ContentRepository 或 Nyholm Response。
