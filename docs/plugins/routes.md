# Plugin 路由

## 推荐结构

Plugin 的 HTTP 路由放在自身目录，不写入 Core：

```text
plugins/ExamplePlugin/
├── Plugin.php
└── routes/
    └── web.php
```

`Plugin::register()` 负责准备 Plugin 服务、Component 等依赖，然后通过受控 Registrar 加载路由：

```php
public function register(PluginRegistrar $registrar): void
{
    $service = new ExampleService();
    $registrar->routes()->load(__DIR__ . '/routes/web.php', $service);
}
```

## 路由文件契约

Plugin 路由文件必须是 `.php` 文件，并返回注册 Closure。Registrar 会自动作为第一个参数传入，其余参数来自 `load()`：

```php
return static function (RouteRegistrar $routes, ExampleService $service): void {
    $routes->post('/example', function (ServerRequestInterface $request) use ($service): ResponseInterface {
        return $service->handle($request);
    })->name('example.store');
};
```

Plugin 可以使用 Route Name、Prefix Group 和 Route Middleware，但不得访问 Router dispatch、Core 路由集合或修改其他路由。注册前需要避免冲突时可调用 `RouteRegistrar::has()`。

## 生命周期与安全

路由只在 Plugin 已安装且启用时注册，顺序继续由 PluginManager 的发现和 boot 流程控制。停用后的下一次请求不会加载该 Plugin 路由。

路由文件不绕过 Authentication、Permission、CSRF 和 Validation。Plugin 的后台写操作仍必须接入相应安全 Middleware 或在受控服务边界中完成检查；公开写操作必须明确执行 CSRF、输入校验与输出转义。

Plugin 应使用 `PluginRegistrar::routes()` 获取受控路由入口，不直接依赖 Router。
