# 安装 Jinstan

## 环境要求

- PHP 8.3 或更高版本。
- PHP 扩展：PDO，以及目标数据库对应的 `pdo_sqlite`、`pdo_mysql` 或 `pdo_pgsql`；正式包校验还需要 Zip。
- Web Server 的站点根目录必须指向 `public/`。
- `storage/` 及其子目录必须允许 PHP 进程写入。

## 浏览器安装

解压正式包后访问 `/install`。安装器会依次检查环境、连接数据库、执行 Migration、写入站点设置、创建管理员并写入安装锁。

SQLite 是默认选项，不需要预先创建数据库。选择 MySQL、MariaDB 或 PostgreSQL 时，应提前创建空数据库和最小权限数据库账户。

管理员密码至少 6 位。安装成功后会跳转到首页，`storage/installed.php` 会阻止安装器再次运行。

## Web Server

必须禁止直接访问 `core/`、`config/`、`storage/`、`plugins/` 与 `site/`。推荐直接把 Document Root 设置为发布目录中的 `public/`，不要把整个项目目录暴露为网站根目录。

## 安装后检查

1. 访问 `/login` 并使用刚创建的管理员登录。
2. 打开后台数据库工具，执行完整性检查。
3. 设置站点 URL、默认语言、时区和 Theme。
4. 确认 Web Server 可以写入缓存、日志、媒体与备份目录。

CLI 仅用于开发和运维，不是普通用户安装的前置条件。
