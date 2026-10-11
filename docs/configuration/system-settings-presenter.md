# 系统设置页面展示数据

`SystemSettingsPresenter` 负责后台系统设置页的只读 View Model，包括已保存设置及默认值、可选 Page、时区、日期格式、前后台语言、Theme、SNS 平台、自定义设置和活动页签。

保存时，`SettingsAdminController` 只协调请求验证、命令调用和重定向。`SaveSystemSettings` 将 `SystemSettingsRequest` 的已验证结果映射为设置键，解析当前请求的 HTTP/HTTPS Origin，并通过 `SettingsRepository::saveSystem()` 在同一事务中保存普通设置和自定义设置。

请求 URI 没有合法的 HTTP/HTTPS Scheme 或 Host 时，保存命令沿用现有 `site.url` 并移除末尾斜线，避免后台相对地址覆盖已配置的站点来源地址。

Presenter 会合并 Core 必需的保留路由并去重；已保存的 Homepage 或 404 Page 不再存在时，表单选项安全回退为 `0`。未知或非字符串页签输入回退到 `site`。Controller 只把 Presenter 结果交给 `.jst` View，不遍历 Content 或拼装页面展示结构。
