# Runtime Storage Boundary

Core、Theme 和 Plugin 目录中的资源是可替换代码与只读资产。运行期间生成的数据统一写入 `storage/`，Core 更新不得覆盖这些内容。

当前路径约定：

| 数据 | 路径 |
| --- | --- |
| SQLite 数据库 | `storage/database/database.sqlite` |
| 生成的数据库配置 | `storage/config/database.php` |
| 安装锁 | `storage/installed.php` |
| 维护锁 | `storage/maintenance.lock` |
| 应用日志 | `storage/logs/app.log` |
| 后台 Latte 缓存 | `storage/cache/latte-admin/` |
| Installer Latte 缓存 | `storage/cache/latte-installer/` |
| 前台 Latte 缓存 | `storage/cache/latte/`、`storage/cache/latte-html/` |
| Content 附件 | `storage/content-attachments/` |
| 数据库备份 | `storage/backups/` |
| Core 更新工作区 | `storage/updates/` |

生产代码不得使用系统临时目录保存持久数据。测试和性能基线可以使用系统临时目录，但必须使用隔离前缀并在完成后清理。
