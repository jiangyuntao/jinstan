# DatabaseServiceProvider

## 职责

`Jinstan\Core\Providers\DatabaseServiceProvider` 是数据库基础设施注册入口，负责：

- 注册 `Infrastructure\\Database\\ConnectionFactory`。
- 注册 `Infrastructure\\Database\\MigrationRunner`。
- 读取 Foundation Provider 已加载的数据库配置。
- 仅在数据库配置非空时创建并注册 `Connection`。

Provider 不执行 Migration、不修改 Schema，也不在 Plugin boot 或普通业务请求中隐式建表。

当前 Core Migration 是面向全新安装的单一基线 `Version202610030001`，直接创建最终 Schema。本预发布阶段不保留早期开发库的逐版数据升级链；重建开发环境应从空数据库执行 `/install`。

## 未安装状态

新环境尚未产生 `storage/config/database.php` 时：

- `ConnectionFactory` 可供 Installer 使用。
- `MigrationRunner` 可供 Installer 使用。
- Container 中不存在 `Connection`。
- `/install` 可以在没有数据库连接的情况下显示。

这保证普通用户仍然只需访问 `/install`。

## 已安装状态

存在有效数据库配置时，Provider 创建当前 Driver 对应的 `Connection` 并注册到 Container。当前 Bootstrap 后续装配复用同一 Connection，不再自行重复创建连接工厂。

## 架构边界

DatabaseServiceProvider、Connection 和 ConnectionFactory 当前均属于 Core Internal。Plugin 和 Theme 不得从 Container 获取具体 Connection。

当前依赖方向为：Application Service 依赖 Repository Contract；Infrastructure Repository 依赖 Connection；Controller、View、Theme 和普通 Plugin 不依赖 Connection。Architecture Tests 持续检查该边界。

## 测试

`tests/Integration/DatabaseServiceProviderTest.php` 覆盖：

- 未配置数据库时只注册基础设施。
- 已安装 SQLite 站点可以注册并使用 Connection。
- Provider 接入后完整路由快照与 HTTP 基线保持不变。
