# 后台主题管理

`ThemeManagerQuery` 负责后台主题管理页的只读 View Model，包括主题清单、当前主题、可编辑目录树、选中文件源码、活动主题以及当前主题是否允许删除。

主题和文件参数必须是标量字符串，并且必须存在于 `ThemeFileManager` 返回的受控清单中。异常嵌套参数不会被强制转换；不存在的主题或文件由 Controller 转换为本地化 404 响应。文件内容只在完成清单校验后读取。

`Infrastructure\\Filesystem\\Theme\\ThemeFileManager` 作为文件系统基础设施由 `AdminServiceProvider` 注册并显式注入 Query 与 Controller。该适配器独立负责真实路径边界、符号链接、扩展名白名单、默认主题保护和底层文件操作。

`ThemeWriter` 负责后台主题写入用例：覆盖文件、创建或删除主题、创建／重命名／删除目录，以及创建或删除模板文件。它规范化标量输入，保留源码原始空格，并在覆盖或删除前验证明确确认值。活动主题由 `SettingsRepository` 解析，仍不能被删除。

Writer 返回统一的主题和文件重定向上下文，不生成 HTTP Response。Controller 只负责调用、将文件领域错误翻译为 422，并通过命名路由返回管理页。
