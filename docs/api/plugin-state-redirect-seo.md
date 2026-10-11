# Plugin State、Redirect 与 SEO 持久化边界

## Plugin State

- `PluginStateReadRepository`：读取已安装版本和启用状态。
- `PluginStateWriteRepository`：保存版本与启用状态。
- `PluginStateRepositoryContract`：PluginManager 使用的组合契约。

PluginManager 负责生命周期和 Manifest 规则，不是 Repository。数据库实现只持久化状态；插件自定义设置通过 `SettingsRepositoryContract` 读写，卸载时按 Manifest 定义清理。

## Redirect

- `RedirectReadRepository`：按来源路径查询 301 目标。
- `RedirectWriteRepository`：新增 Redirect。
- `RedirectRepositoryContract`：组合契约。

数据库实现位于 `Infrastructure\\Persistence\\SEO\\RedirectRepository`，前台内容查询依赖 `RedirectReadRepository`。

前台 Content Query 只依赖读取接口。接口不暴露状态码 SQL 或 Connection；当前实现仍只返回已启用的 301 Redirect。

Redirect 仅接受以 `/` 开头的站内路径。来源路径不能包含 Query 或 Fragment；目标路径可以包含 Query 和 Fragment，但拒绝协议 URL、`//` 协议相对 URL、反斜线、控制字符和响应头换行。读取旧版本已经写入的不安全目标时返回 `null`，不会生成开放重定向。

## SEO

`View\Presenters\Frontend\SeoRenderer` 只依赖 `SettingsReadRepository` 获取标题模板、站点信息、描述和 robots 默认值，不再要求具体 Settings 数据库实现。SEO 输出继续执行 HTML 属性转义。
