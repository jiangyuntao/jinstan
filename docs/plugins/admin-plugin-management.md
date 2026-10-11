# 后台插件管理

`PluginAdminQuery` 负责后台插件清单与插件设置页读取。设置页只允许访问已安装且声明了设置定义的插件；不存在、已卸载或没有设置项的插件由 Controller 转换为 404。

`PluginAdminPresenter` 负责插件清单和设置表单 View Model，包括 CSRF Token 与本地化页面标题。Query 和 Presenter 不调用插件生命周期，也不保存设置。

插件发现、Manifest 校验、安装、启用、停用、升级、卸载及设置定义验证继续由 `PluginManager` 提供。后续写入迁移通过独立 Application Command 协调这些能力。

`PluginWriter` 负责后台启用／停用意图解析、设置提交和卸载调用。状态值必须是可识别的布尔值，旧式 `action` 参数只接受 `enable` 或 `disable`；设置集合必须是数组。

每个已声明的设置值必须是字符串，嵌套数组不会再被 PHP 强制转换为 `Array`。URL、JSON 对象、必填与长度规则仍由 `PluginManager` 根据 Manifest 定义验证。卸载操作继续经过 Authentication、Permission、CSRF 和前端明确影响确认。
