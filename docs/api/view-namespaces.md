# View Namespace

Core 的服务端 View 使用 `namespace::path.jst` 标识。`ViewNamespaceRegistry` 负责将命名空间映射到受控目录，`Infrastructure\\View\\NamespacedViewLoader` 负责 Latte 模板读取和相对引用解析。

Plugin Manager 只通过 `PluginViewRegistrar` 注册插件 View 目录，不依赖 Registry 的解析能力。公开的 `ViewRegistrar` 继承该最小注册契约，是 1.0 Plugin API 的 View 边界。

后台模板默认注册为 `admin` 命名空间。现有调用仍可使用：

```php
$renderer->render('users', '用户');
```

渲染器会将其规范化为 `admin::users.jst`。模板内部的 `{extends 'layout.jst'}` 和 `{include 'partials/form-actions.jst'}` 会继续在当前命名空间中解析。

Plugin 根目录存在 `views/` 时，Plugin Manager 会在发现 Manifest 时自动用 Manifest 的 `name` 注册命名空间。例如 `external-order` Plugin 的模板可以使用：

```php
$renderer->render('external-order::settings.jst', 'External Order');
```

Core 或测试需要手动注册扩展 View 时，应在应用装配阶段创建并传入 Registry：

```php
$views = new ViewNamespaceRegistry();
$views->register('example-plugin', $pluginPath . '/views');

$renderer = new AdminViewRenderer(
    $adminViewsPath,
    $cachePath,
    $urls,
    viewNamespaces: $views,
);
```

随后同样可使用完整名称渲染：

```php
$renderer->render('example-plugin::settings.jst', 'Example');
```

命名空间只能由小写字母、数字和连字符组成，且必须以字母开头。Registry 禁止替换已注册命名空间，也拒绝绝对路径和 `..` 目录逃逸。所有模板仍必须使用 `.jst` 扩展名。
