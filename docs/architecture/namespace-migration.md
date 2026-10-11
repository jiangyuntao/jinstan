# Namespace 迁移状态

Phase 12 的 Namespace 迁移已经完成。当前目录中的类名与 Namespace 是唯一受支持的 API；迁移前的旧 Namespace、`class_alias` 文件、兼容子类及旧 Container ID 均已删除。

## 当前边界

- HTTP Controller 与 Request 位于 `Jinstan\Core\Http`。
- Application Query、Service 与用例输入位于 `Jinstan\Core\Application`。
- Presenter 与 View 契约位于 `Jinstan\Core\View`。
- PDO、文件系统、模板引擎、日志和具体 Repository 位于 `Jinstan\Core\Infrastructure`。
- Content、Field、Plugin、Theme 等稳定扩展契约仍位于各自 Feature Namespace。
- `Jinstan\Core\Database\Migration` 与 `Jinstan\Core\Database\Schema` 继续作为 Plugin Migration 的公共 DSL。

## 迁移要求

Site、Theme、Plugin 与测试必须直接引用当前类名。升级到本版本时，调用方需要同步修改 Namespace；Core 不再自动加载旧类名，也不会为旧类名注册第二个 Container ID。

常用当前入口包括：

| 能力 | 当前类型 |
| --- | --- |
| 认证 | `Jinstan\Core\Application\Auth\Services\Authenticator` |
| 安装 | `Jinstan\Core\Application\Installer\Services\InstallApplicationService` |
| Core 更新 | `Jinstan\Core\Application\Update\Services\CoreUpdater` |
| 数据库连接 | `Jinstan\Core\Infrastructure\Database\Connection` |
| Content Repository | `Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository` |
| Settings Repository | `Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository` |
| Theme Renderer | `Jinstan\Core\Infrastructure\View\ThemeRenderer` |
| Plugin Manager | `Jinstan\Core\Application\Plugin\Services\PluginManager` |

## 约束

- 生产代码和测试不得新增 `class_alias()`。
- Provider 只注册当前类型及其公开契约，不注册历史类名。
- 新的 Namespace 调整必须一次性迁移 Core、官方 Plugin、测试和文档。
- 发布前通过静态扫描确认旧 FQCN 与兼容包装器没有重新出现。
