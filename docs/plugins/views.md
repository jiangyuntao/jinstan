# Plugin Views

Plugin View 放在根目录的 `views/`，所有 Latte 模板必须使用 `.jst`：

```text
plugins/ExamplePlugin/
└── views/
    ├── admin/
    │   └── settings.jst
    └── components/
        └── example.jst
```

Plugin Manager 会用 Manifest 中的 name 自动注册 View Namespace。完整模板名为 `plugin-name::relative/path.jst`：

```php
$html = $registrar->viewRenderer()->render(
    'example-plugin::admin/settings.jst',
    '插件设置',
    ['settings' => $settings],
);
```

`views()` 返回只写的 `ViewRegistrar`，可用于显式注册额外目录；`viewRenderer()` 返回受控的 `ViewRenderer`，只提供渲染能力。Plugin 不会获得 Latte Engine、Loader、模板缓存路径或其他命名空间清单。

命名空间只能包含小写字母、数字和连字符，同一命名空间不能替换为另一个目录。模板路径禁止绝对路径和 `..` 目录穿越，并被限制在已注册目录内。View 使用 Latte 默认转义；只有经过明确安全处理的数据才可以使用 `|noescape`。

Plugin Component View 的 Theme Override 仍遵循独立解析顺序：Theme Override → Plugin View → Core View，详见 Component View Resolution 文档。
