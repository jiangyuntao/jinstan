# Controller Action 与 Route Registrar

## Controller Action

除 Closure 和已实例化 Controller 外，Router 支持主流框架常见的类名 Action：

```php
$router->get('/articles/{id}', [ArticleController::class, 'show'])
    ->name('articles.show');
```

Router 在请求匹配后才从 PSR Container 获取 Controller，因此路由注册不需要提前构造 Controller。Controller 必须已注册到 Container，目标方法必须可调用，并继续接收当前 Request 与路由参数：

```php
public function show(ServerRequestInterface $request, array $parameters): ResponseInterface
{
    // ...
}
```

未注册的 Controller 或不可调用的方法会抛出明确的运行时异常，并由 HTTP Exception Handler 按环境统一处理。

## 受控 Route Registrar

`RouteRegistrar` 是 Core 面向 Plugin 和独立路由文件提供的公共注册边界。它只暴露：

- `get()`
- `post()`
- `group()`
- `has()`：注册前检查 Method 与 Path 是否已存在。
- `load()`：加载并执行返回注册 Closure 的 PHP 路由文件。

返回的 `Route` 可以继续配置名称和 Middleware。Registrar 不暴露请求 dispatch、命名路由查询或内部路由集合，扩展因此只能注册自身能力，不能接管 Core 路由运行过程。

Plugin 使用：

```php
$registrar->routes()->group(['prefix' => '/catalog'], function (RouteRegistrar $routes): void {
    $routes->get('/offers', $handler)->name('catalog.offers');
});
```

Plugin 应把路由声明放入自身 `routes/web.php`，并通过 `$registrar->routes()->load()` 加载。详细约束参见 `docs/plugins/routes.md`。
