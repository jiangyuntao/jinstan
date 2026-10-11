# 1.0 测试矩阵

## HTTP 与安全

| 能力 | 主要测试 |
| --- | --- |
| 浏览器安装、安装锁、管理员创建 | `InstallerTest`、`CoreHttpBaselineTest` |
| Login、Session、CSRF、Permission | `CoreHttpBaselineTest`、Middleware Unit Tests |
| Content CRUD、发布与上传回滚 | `CoreHttpBaselineTest`、`ContentUploadCleanupTest` |
| 安全响应头与异常响应 | `RouteServiceProviderTest`、`HttpResponseBoundaryTest` |
| 发布包安装、登录和 CLI | `scripts/verify-release.php` |

## Domain 与 Integration

| 能力 | 主要测试 |
| --- | --- |
| Content、Translation、Relation | `ContentServiceTest`、`ContentPresentationTest` |
| Field normalization、validation、rendering | Field Unit 与 Integration Tests |
| Taxonomy | `ContentTaxonomyTest`、`TaxonomyDatabaseTest`、`TaxonomyListingTest` |
| Theme、Template、Component | `HomepageTest`、`ContentPresentationTest` |
| Plugin 生命周期、迁移、路由和 Public API | `PluginServiceProviderTest`、`RouteInventoryTest`、Architecture Tests |
| User、Role、Permission | `UserRepositoryTest`、`RoleRepositoryTest`、`AdminMenuPermissionTest` |
| Backup、Database Maintenance、Core Update 回滚 | `BackupServiceTest`、`CoreUpdaterTest`、Database Tests |

## 数据库与 PHP

本地默认使用 SQLite。GitHub Actions 在 PHP 8.3 上运行完整 SQLite 套件，并分别对 MySQL 8.4、MariaDB 11.4 和 PostgreSQL 17 运行关键跨数据库集成测试。发布前还需在当前锁文件上运行 Composer 与 npm 安全审计。

## 发布门槛

正式版本必须同时满足：

1. PHPUnit 完整套件通过，只有未配置外部数据库时允许对应的 4 个显式 Skip。
2. PHP CS Fixer dry-run 通过。
3. PHP 8.3 CI 与三类数据库 Job 通过。
4. `composer audit --locked` 和 `npm audit --omit=dev` 无漏洞。
5. 正式 ZIP 通过独立解压、Web Installer、管理员登录、后台访问、资源哈希和 CLI 验证。
6. Previous Stable 到目标版本的升级与回滚测试通过；1.0.0 是首个 Stable，因此以全新安装和失败回滚测试作为基线。
