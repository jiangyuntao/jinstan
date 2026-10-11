# 后台缓存与日志

后台缓存与日志页面通过 `system.manage` 权限保护，所有清理请求同时要求有效的 CSRF Token，并在前端显示二次确认。

## 缓存

`CacheLogsQuery` 只编排缓存和日志查询，`CacheLogsMaintenance` 只编排清理命令；实际目录扫描、日志尾部读取、大小计算与文件删除统一委托给 `Infrastructure\\Filesystem\\System\\RuntimeStorage`。目录中的 `.gitkeep` 会被保留，以维持发布包所需的空目录结构。

缓存清理不会修改站点配置、Content、Theme 或 Plugin 文件。清理后，模板等运行期缓存会在后续请求中重新生成。

## 日志

日志页面显示 `storage/logs/app.log` 的最后 10,000 字节，并单独展示完整日志文件大小，避免后台页面一次读取和渲染整个大文件。

清理日志会截断当前 `app.log`，不会删除日志目录。该操作不可恢复，因此后台必须保留明确的二次确认提示。

## 扩展边界

- `CacheLogsQuery`：只读文件系统查询。
- `CacheLogsPresenter`：本地化标题、容量格式及页面 View Model。
- `CacheLogsMaintenance`：缓存和日志清理操作。
- `CacheLogsController`：协调请求、响应及命名路由跳转。
