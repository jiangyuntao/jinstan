# Plugin Translations

Plugin 后台语言包放在 `lang/admin/`，文件名使用 locale：

```text
plugins/ExamplePlugin/
└── lang/
    └── admin/
        ├── zh-CN.php
        └── ko.php
```

每个文件返回 `array<string, string>`：

```php
return [
    'settings.title' => '插件设置',
    'welcome' => '欢迎，{name}！',
];
```

Plugin Manager 发现目录后，会使用 Manifest 中的 Plugin name 自动注册 Translation Namespace。模板通过完整命名空间调用现有 `t()`：

```latte
<h1>{t('example-plugin::settings.title')}</h1>
<p>{t('example-plugin::welcome', ['name' => $userName])}</p>
```

PHP Presenter 或 Service 若已注入 `Translator`，使用相同 key 调用 `translate()`。解析顺序为当前后台 locale → fallback locale（默认 `zh-CN`）→ 返回原 key。Plugin key 必须包含 `<plugin-name>::` 前缀，防止覆盖 Core 或其他 Plugin 文案。

Plugin 也可以通过 `PluginRegistrar::translations()` 显式注册其他具名目录。命名空间只能由小写字母、数字和连字符组成，同一命名空间不得指向两个不同目录。语言包属于可信 Plugin 代码的一部分，必须返回数组；非字符串值会被忽略。
