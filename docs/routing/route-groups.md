# 路由分组与路由 Middleware

## Prefix Group

`Router::group()` 可以给一组路由统一添加路径前缀，并支持嵌套：

```php
$router->group(['prefix' => '/admin'], function (Router $router): void {
    $router->get('/', [$dashboard, 'index'])->name('admin.dashboard');

    $router->group(['prefix' => '/content'], function (Router $router): void {
        $router->get('/create', [$content, 'create'])->name('admin.content.create');
    });
});
```

以上路由分别匹配 `/admin` 与 `/admin/content/create`。前缀会统一规范化，调用方无需处理首尾斜线。

## Middleware Group

组和单条 Route 都可以注册 Middleware：

```php
$router->group([
    'prefix' => '/admin',
    'middleware' => [$adminSecurity],
], function (Router $router) use ($permission): void {
    $router->get('/settings', [$settings, 'index'])
        ->middleware($permission)
        ->name('admin.settings');
});
```

Middleware 必须实现 `Jinstan\Core\Http\Contracts\Middleware`。嵌套 Group Middleware 按从外到内的顺序合并，Route 自身的 Middleware 最后进入管线；响应则按相反顺序返回。任意 Middleware 均可直接返回 Response 来短路后续 Middleware 与 Controller。

## 执行边界

完整顺序为：

```text
Kernel 全局 Middleware
→ Router 匹配
→ 外层 Group Middleware
→ 内层 Group Middleware
→ Route Middleware
→ Controller
```

因此，全站安全与通用响应处理继续放在 Kernel；只有匹配到特定路由后才有意义的权限或功能逻辑放在 Route Middleware。

Group 使用作用域栈并在回调结束时恢复。即使注册回调抛出异常，也不会把当前前缀或 Middleware 泄漏给后续路由。
