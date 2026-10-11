# Plugin Public API

本文档是 Phase 11 完成后的 Plugin API 边界。未列入的 Core 具体实现默认属于 Internal API，不承诺兼容。

## 生命周期与入口

- `PluginLifecycle`
- `PluginServiceProvider`
- `PluginRegistrar`

## Registrar 与运行能力

- `RouteRegistrar`、`Route`
- `PluginServiceRegistrar`
- `ComponentRegistrar`、`Component`
- `ContentTypeRegistrar`、`ContentTypeDefinition`
- `FieldTypeRegistrar`、`FieldType`、`FieldDefinition`
- `EventListenerRegistrar` 和 `ContentCreated`、`ContentUpdated`、`ContentPublished`、`ContentUnpublished`、`ContentDeleted`
- `TranslationRegistrar`
- `ViewRegistrar`、`ViewRenderer`
- `ContentReadRepository`
- `SettingsReadRepository`
- `CsrfTokenProvider`
- `RateLimiter` 与 `RateLimitResult`
- `ResponseFactory`
- `SchemaRegistrar`，用于注册 Content JSON-LD provider
- `Migration` 与跨数据库 `Schema`

PSR HTTP Message 接口也是 Route、Controller 和 Response 的公共边界。

公开写入或调用第三方服务的 Plugin Route 应调用 `$registrar->rateLimiter()`。例如：

```php
$limit = $registrar->rateLimiter()->consume('plugin-route:ip:' . $clientAddress, 10, 60);
if (!$limit->allowed) {
    return $registrar->responses()->text('请求过于频繁。', 429, [
        'Retry-After' => (string) $limit->retryAfter,
    ]);
}
```

限速键不得直接包含密码、Token 或其他秘密。Core 默认实现适用于单节点；多节点部署可替换为实现相同 Contract 的共享存储适配器。

## Internal API

Plugin 不得依赖以下基础设施实现：

- `Container`
- `Connection` 和数据库驱动细节
- `ContentRepository`、`SettingsRepository` 等具体 Repository
- `Router`
- `ComponentRegistry`、`ContentTypeRegistry`、`FieldTypeRegistry`
- `EventDispatcher`
- `TranslationRegistry`
- `ViewNamespaceRegistry`、Latte Engine 和 Loader
- 具体 Session CSRF 实现 `CsrfToken`

官方 Plugin 的依赖由 `PluginPublicApiBoundaryTest` 持续检查。新增 Public API 必须同时提供稳定 Contract、测试、文档和最小使用示例；不能仅为某个官方 Plugin 暴露内部实现。

## 版本边界

1.0 起，Public API 以本文档列出的入口为准，不保留早期 `Plugin` 与 `PluginContext` 兼容层。后续破坏性变更执行弃用周期。
