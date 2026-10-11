# Installer Application Service

普通用户仍通过 `/install` 完成安装，不需要运行 CLI。

安装流程由 `InstallApplicationService` 按固定顺序协调：

1. 检查 Installation Lock。
2. 由 Application 层的 `InstallationInput` 校验站点、管理员和数据库输入。
3. 通过 `InstallationDatabaseGateway` 执行连接探测、Core Migration 和初始数据写入。
4. 数据库适配器在事务中创建管理员、角色关系和初始站点设置。
5. 通过 `DatabaseConfigGateway` 写入 `storage/config/database.php`。
6. 通过 `InstallationLockGateway` 创建 Installation Lock。

只有全部步骤成功后才会创建锁文件。初始数据写入失败时会回滚 Seeder 事务；连接错误会在 Migration 前返回安装页面。

`InstallPresenter` 负责环境检查列表、表单 Action、CSRF 隐藏字段和安全错误输出。`InstallController` 只负责 CSRF、环境检查、请求输入、错误响应和成功跳转，不直接拼接页面 HTML，并直接依赖 `InstallApplicationService`。

安装管理员密码与后台用户创建规则一致，最少为 6 位；浏览器表单约束和服务端校验使用相同下限。

数据库密码只写入站点私有的数据库配置文件，不得写入日志或错误上下文。

`InstallApplicationService` 只依赖上述 Installer 契约和输入校验器，不接触 HTTP Request、数据库连接、Migration Runner、Seeder 或文件系统实现。默认适配器由 `InstallerServiceProvider` 在组合根装配。
