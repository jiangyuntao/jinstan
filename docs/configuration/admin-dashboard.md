# 后台概览

后台概览汇总 Content Type 数量、发布状态、运行环境、主要依赖版本和数据库状态。页面只提供只读信息，不执行维护或写操作。

## 数据边界

`DashboardQuery` 负责读取：

- 已注册 Content Type 及其内容数量。
- 已发布与草稿数量。
- PHP、Core 和主要前端／Composer 依赖版本。
- 数据库驱动、表数量、Migration 数量和 SQLite 完整性结果。

非 SQLite 数据库的完整性状态显示为“由数据库服务器维护”；Driver-specific 的检查和优化继续由 Database Tools 处理。

`DashboardPresenter` 负责默认 Content Type 名称本地化、后台内容链接、系统和数据库标签，以及供图表读取的安全 JSON。自定义 Content Type 保留注册时提供的名称。

`DashboardController` 只协调 Query、Presenter 和 HTML Response，不直接查询 Repository 或 Connection。
