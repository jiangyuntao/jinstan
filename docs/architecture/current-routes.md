# 当前路由基线

## 目的

本文档记录现代化重构开始前的路由事实。可执行快照位于 `tests/Architecture/RouteInventoryTest.php`，文档只解释分组与迁移边界，避免维护两份容易漂移的完整清单。

## 当前注册来源

| 来源 | 职责 | 当前状态 |
| --- | --- | --- |
| `core/routes/install.php` | 健康检查与 Installer | 已迁移至独立路由文件 |
| `core/routes/auth.php` | 登录页面与登录提交 | 已迁移至独立路由文件 |
| `core/routes/admin.php` | Admin 与 Logout | 按 Plugin 与 Content 注册时序分区 |
| `core/routes/web.php` | Frontend、Media、Sitemap 与动态 Content | 按既有优先级分区 |
| `core/src/Http/Routing/ContentRoutes.php` | 根据 Content Type 生成列表、详情及 Locale 路由 | 通过受控 Route Registrar 注册 |
| `plugins/HelloPlugin/routes/web.php` | Hello Plugin 示例路由 | Plugin boot 时受控加载 |
| `plugins/ExternalOrderPlugin/routes/web.php` | External Order 写入路由 | Plugin boot 时受控加载 |

## 基线范围

路由快照覆盖：

- 未安装状态下的 `/` 与 `/install`。
- 已安装状态下的首页、Locale 首页与 Sitemap。
- 登录、退出和全部 Admin 页面。
- Content、Content Type、Taxonomy、Navigation、Plugin、Theme、Database、User、Role、Settings、Cache 和 Logs。
- Media 的公开与后台访问路径。
- Article、Page、Product 的列表和详情路径。
- Locale 前缀的 Content 列表和详情路径。
- 两个官方示例 Plugin 的路由。

## 当前稳定要求

- 1.0 声明的 HTTP Method 和 Path 必须保持稳定。
- 动态 Content Type 路由的注册顺序必须保持静态列表优先、具体详情其次、通用 Page 最后。
- Locale 路由必须继续支持当前 Locale 正则。
- Plugin 路由必须继续只在 Plugin 启用时注册。

## 自动验证

`RouteInventoryTest` 在一次性 SQLite 安装站点上实际运行 Bootstrap，并比较完整有序路由表。该快照是 1.0 路由回归基线，不会把 Router 内部结构提升为 Public API。
