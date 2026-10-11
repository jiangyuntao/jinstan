# Admin Presenter 与 Page View Model

后台 Controller、Presenter 与 `AdminPageView` 只依赖 `AdminView` 契约提供的 `render()` 和 `translate()`；默认 Latte 实现为 Infrastructure 的 `AdminViewRenderer`，由 Admin Provider 统一装配。

后台 Presenter 负责把 Query 或领域结果转换为模板所需的展示数据。新迁移的页面统一返回 `AdminPageView`，而不是依赖 `['view', 'title', 'data']` 这类约定式数组。

```php
return new AdminPageView(
    'plugin-settings',
    $views->translate('插件设置'),
    ['plugin' => $plugin],
);
```

`AdminPageView` 是不可变对象，明确提供：

- `template`：Core 后台短模板名，或完整的 Plugin View Namespace 名称。
- `title`：已准备好的页面标题。
- `data`：仅供 View 展示的数据。

Core 短模板名不包含扩展名，例如 `plugin-settings`。Plugin 模板使用完整名称，例如 `example-plugin::settings.jst`。对象会拒绝绝对路径、目录逃逸和不符合约定的模板名称。

Controller 只需把 View Model 交给 Renderer：

```php
return $responses->html($page->render($views));
```

Presenter 不应返回 Response、访问数据库或执行写操作。复杂查询属于 Query，状态变更属于 Application Service／Writer。现有返回数组的 Presenter 将按模块渐进迁移，不需要为统一类型一次性重写所有后台页面。
