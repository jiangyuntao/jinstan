# InstallerServiceProvider

## 职责

`InstallerServiceProvider` 在所有环境注册安装所需服务：

- `Infrastructure\\Filesystem\\Installer\\InstallationLock`
- `EnvironmentGateway`（默认绑定 `EnvironmentChecker`）
- `InstallerView`（默认绑定 Latte `InstallerViewRenderer`）
- `InstallApplicationService`
- `InstallController`

它复用 Database Provider 注册的 ConnectionFactory 和 MigrationRunner，但不要求 Container 中已经存在 Connection。

## 未安装流程

```text
Foundation
→ Database 基础设施
→ Installer 服务
→ RouteServiceProvider 注册 /install
```

普通用户仍通过 `/install` 完成环境检查、数据库选择、站点设置、管理员创建、Migration 和 Installation Lock。

## 安全边界

- 已存在 Installation Lock 时，Installer 返回 404。
- POST 安装继续验证 CSRF。
- Provider 注册过程不执行 Migration。
- Migration 只在用户提交有效安装请求后执行。

## 测试

`InstallerServiceProviderTest` 验证未配置数据库时仍可注册完整 Installer 服务；HTTP 基线继续验证安装前可访问以及安装后锁定。
