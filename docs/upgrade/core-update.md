# Core Update

`Application\\Update\\Services\\CoreUpdater` 负责协调一次 Core 发布切换：

1. 校验暂存 Core 及其资源清单。
2. 校验并发布带版本号的 Core 前端资源。
3. 开启维护模式并备份当前 Core。
4. 切换新 Core，执行 Migration。
5. 校验已安装资源并执行 Health Check。
6. 成功后解除维护模式；失败时恢复旧 Core、清理本次发布的资源并解除维护模式。

应用服务只依赖以下内部边界：

- `CoreUpdateStorage`：Core 与资源目录的校验、发布、切换和回滚。
- `CoreMigrationGateway`：对当前站点数据库执行新 Core Migration。
- `MaintenanceModeGateway`：控制维护状态。

默认实现位于 Infrastructure。资源 Manifest、数据库连接和具体 Migration Runner 不会暴露给应用层。调用方必须使用 `Jinstan\\Core\\Application\\Update\\Services\\CoreUpdater` 并显式注入这些契约。

升级包必须包含相互匹配的 `core/src/Version.php`、`core/assets/manifest.php` 和 `public/assets/core/<release>/`。升级流程不会写入 `site/`、`plugins/`、`themes/`、用户配置或其他 Storage 数据。
