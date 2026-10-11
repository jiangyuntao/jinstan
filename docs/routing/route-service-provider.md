# RouteServiceProvider

## 职责

`RouteServiceProvider` 是当前 Core HTTP 路由的唯一装配入口。Bootstrap 不再包含具体 URL、Controller 或路由注册代码。

Provider 当前负责：

- 加载 `core/routes/install.php`。
- 加载 `core/routes/auth.php`。
- 在 Plugin 路由注册前加载 `core/routes/admin.php` 的 `before_plugins` 分区。
- 在 Plugin 路由注册后加载 Admin 的 `after_plugins` 分区，并在动态 Content 路由后加载 `after_content` 分区。
- 分区加载 `core/routes/web.php` 中的 Frontend、Locale、Sitemap、Media 与动态 Content 路由。
- Login 与全部 Admin 路由。
- Plugin 路由前后的顺序协调。
- 动态 Content Type 列表和详情路由。
- Not Found Handler。
- 当前 `Http\Application` 的最终装配。

## 生命周期与路由顺序

为保持重构前行为，路由分两段注册：

```text
RouteServiceProvider::register()
→ Installer / Frontend / Login / Admin 前半段

PluginServiceProvider::boot()
→ Plugin Routes

RouteServiceProvider::boot()
→ Plugin Admin / Admin 后半段 / Content Routes
→ 创建 Http Application
```

这种分段明确固定 Plugin、静态路由和动态 Content 路由的注册优先级。Core 与官方 Plugin 路由均位于独立路由文件，并通过 Route Name、Group 和 Middleware 注册。

## 未安装状态

没有 Connection 时只注册：

- `GET /`
- `GET /install`
- `POST /install`

随后仍创建 Http Application，因此新环境不需要 CLI 即可进入 Installer。

## 已具备的路由能力

当前 Router 已支持：

- Route Name。
- Prefix Group。
- Middleware Group。
- URL Generator。

具体用法参见 `docs/routing/named-routes.md`、`docs/routing/route-groups.md`、`docs/routing/controller-actions.md`、`docs/routing/route-files.md` 与 `docs/plugins/routes.md`。

## 测试

- `RouteServiceProviderTest` 验证已安装与未安装请求处理器。
- `RouteInventoryTest` 验证完整有序路由快照。
- `CoreHttpBaselineTest` 验证 Homepage、Installer、Login、Admin 安全和 Content 生命周期。
