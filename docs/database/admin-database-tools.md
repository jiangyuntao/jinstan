# 后台数据库工具

`DatabaseToolsQuery` 负责后台数据库工具页的只读状态，包括驱动、已执行 Migration 数量、完整性检查结果和备份分页。备份页码只接受大于等于 1 的整数，嵌套或非法参数安全回退第一页。

SQLite 使用 `PRAGMA integrity_check` 返回真实检查结果。MySQL/MariaDB 和 PostgreSQL 返回明确的展示键，由 `DatabaseToolsPresenter` 本地化为对应说明。Presenter 同时补充 CSRF Token，并组装数据库工具页 View Model。

Query 和 Presenter 不执行优化、创建备份或删除备份。Driver-specific 维护与备份写操作由 `DatabaseMaintenance` 和现有 `Connection`、`BackupService` 边界负责。

`DatabaseMaintenance` 负责写入型维护用例。SQLite 执行 `VACUUM`，MySQL/MariaDB 仅对经过标识符白名单过滤的表执行 `OPTIMIZE TABLE`，PostgreSQL 执行 `VACUUM (ANALYZE)`。清理操作删除目标与来源相同、必然形成循环的 Redirect，并在执行前显示影响对象确认。备份创建和删除委托给 `Infrastructure\\Filesystem\\Backup\\BackupService`，继续复用 14 位备份标识、路径边界和 Manifest 检查。

Controller 不直接访问 Connection 或 BackupService，只协调 Query、Presenter、Maintenance、错误响应和命名路由重定向。
