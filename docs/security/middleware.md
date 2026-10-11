# 后台安全 Middleware

后台基础安全检查通过 HTTP Middleware 逐步从 Controller 迁移到路由层。Middleware 只处理请求级通用规则；资源归属、活动主题保护、内置内容类型保护等业务判断仍由 Application Service 或 Controller 调用的领域服务负责。

## RequirePermissionMiddleware

`RequirePermissionMiddleware` 接收一个明确的 Permission Key。当前用户不具备权限时返回 `403 Forbidden`，具备权限时继续执行后续 Middleware 和 Controller。

认证先于权限判断执行。`AdminSecurityMiddleware` 作为 `/admin` 的纵深防护，路由组仍显式声明认证、CSRF 和所需权限，新增路由不能只依赖路径前缀。

路由组示例：

```php
$routes->group(['middleware' => [$contentPermission]], static function (RouteRegistrar $routes): void {
    $routes->get('/content-types', [ContentTypeAdminController::class, 'index']);
    $routes->post('/content-types', [ContentTypeAdminController::class, 'save']);
});
```

Content Type 路由是首个迁移组，统一要求 `content.manage`。对应 Controller 已删除 Authentication、Permission 检查及相关依赖，但继续保留 CSRF 与内容类型删除保护等尚未迁移或属于业务边界的校验。

## AuthenticateMiddleware

`AuthenticateMiddleware` 负责登录状态，不负责权限和 CSRF：

- 浏览器请求未登录时通过 `login.show` 命名路由跳转。
- 请求声明接受 JSON 时抛出 `AuthenticationException`，由统一异常处理器转换为 401 JSON。
- 已登录请求继续进入后续 Middleware。

所有 Core Admin 路由已在 `/admin` 路由组声明该 Middleware。Plugin 的后台路由也必须显式声明所需 Middleware；全局防护不替代 Plugin 的权限选择。

## VerifyCsrfTokenMiddleware

`VerifyCsrfTokenMiddleware` 对 GET、HEAD、OPTIONS 直接放行，对其他 HTTP Method 验证 CSRF Token。Token 优先读取 `X-CSRF-Token` Header，其次读取表单 `_token`；失败时抛出 `CsrfTokenMismatchException`，由统一异常处理器转换为 419。

Core Admin 路由组的基础顺序为：

```text
AuthenticateMiddleware
→ VerifyCsrfTokenMiddleware
→ RequirePermissionMiddleware（按业务组配置）
→ Controller
```

Content Type Controller 已删除本地 CSRF 判断。直接调用 Controller 的测试不再承担 HTTP 安全边界测试，CSRF 行为由 Middleware 单元测试和完整 HTTP 回归测试覆盖。

Cache/Logs 路由按 Authenticate → VerifyCsrfToken → `system.manage` 的顺序声明安全 Middleware。Controller 仅保留展示所需的 CSRF Token，不自行检查登录、权限或写请求 Token。集成测试覆盖访客跳转、无权限 403、缺失 Token 419 与合法清理后的反馈跳转。

Role 路由按 Authenticate → VerifyCsrfToken → `users.manage` 的顺序声明安全 Middleware。`RoleAdminController` 不再重复检查登录、权限或写请求 Token，仅保留表单 Token 输出以及内置管理员角色保护、权限白名单等业务规则。

User 管理路由与 Role 共用 Authenticate → VerifyCsrfToken → `users.manage` 路由组。`UserAdminController` 的管理 Action 不再重复执行请求级安全检查，但继续负责当前管理员不能移除自身管理员角色等资源级规则。个人资料页面不要求 `users.manage`，由 Authenticate 与 CSRF Middleware 保护。路由组外的 Logout 继续在 Controller 中独立验证 CSRF Token。

Language 路由按 Authenticate → VerifyCsrfToken → `settings.manage` 的顺序声明安全 Middleware。`LanguageAdminController` 不再重复认证、权限与写请求 Token 检查；Locale 目录白名单、重复语言检查、文字方向校验以及默认语言删除保护仍属于 Controller 当前协调的业务边界。

System Settings 和 Language 使用 Authenticate → VerifyCsrfToken → `settings.manage` 路由组。`SettingsAdminController` 继续负责主题可用性、Locale、SEO、SNS、URL、预定义页面与自定义设置等业务输入规则，但不再重复请求级认证、权限和 CSRF 判断。

Plugin 管理路由同样使用 Authenticate → VerifyCsrfToken → `settings.manage`。`PluginAdminController` 不再重复请求级安全判断；Plugin 是否存在、安装状态、设置定义校验以及 enable、disable、uninstall 生命周期仍由 `PluginManager` 负责。前端二次确认不替代上述服务端安全链。

Theme 管理路由使用 Authenticate → VerifyCsrfToken → `system.manage`。`ThemeAdminController` 不再重复请求级安全判断；`ThemeFileManager` 仍负责真实路径边界、符号链接、可编辑与可创建扩展名、默认主题文件保护，Controller 继续要求覆盖或删除目标的明确确认，并禁止删除活动主题。

Database Tools 路由使用 Authenticate → VerifyCsrfToken → `system.manage`。`DatabaseToolsController` 不再重复请求级安全判断；Driver-specific 完整性检查和优化仍由数据库边界协调，备份创建、分页、标识验证和文件删除继续由 `BackupService` 负责。

Navigation 路由使用 Authenticate → VerifyCsrfToken → `content.manage`。`NavigationAdminController` 不再重复请求级安全判断；菜单标识、Locale、父子层级、循环引用防护、排序集合完整性和链接目标规则仍由 Controller 与 `NavigationRepository` 执行。

Taxonomy 与 Term 路由使用 Authenticate → VerifyCsrfToken → `content.manage`。`TaxonomyAdminController` 不再重复请求级安全判断；删除确认、Content Type 引用保护、父子条目约束、Locale 翻译、Slug 以及分页内排序集合验证仍由 Controller 与 `TaxonomyRepository` 负责。

后台 Media 读取路由使用 Authenticate → VerifyCsrfToken → `content.manage`。GET 请求不会触发 Token 校验，但仍经过同一明确路由组。`MediaAdminController` 只协调读取与响应；哈希目录、允许扩展名、存储路径格式和 MIME 检测继续由 `ContentAttachmentStorage` 负责。

Content 列表、表单、父内容搜索、Taxonomy 搜索及 CRUD 路由使用 Authenticate → VerifyCsrfToken → `content.manage`。`ContentAdminController` 不再重复这些请求级检查；完整 HTML 文档继续在资源处理阶段额外要求 `system.manage`，动态 Field、上传验证、附件失败回滚、Parent Content、Taxonomy 和发布状态规则保持不变。

Dashboard 只要求登录，不附加业务权限。它依赖路由组中的 Authenticate 与 VerifyCsrfToken。

## EnsureNotInstalledMiddleware

Installer 的 GET/POST `/install` 路由使用 `EnsureNotInstalledMiddleware`。安装锁存在时统一返回 404，避免暴露或重新进入安装流程；未安装时请求继续进入 `InstallController`。根健康检查不属于安装表单，不经过该 Middleware。Installation Lock 的创建仍只发生在成功完成 Migration、站点设置和管理员创建之后。

## StartSessionMiddleware

所有 HTTP 请求先经过全局 `StartSessionMiddleware`。它只在 Session 尚未启动时调用 `session_start()`，随后认证、CSRF 和 Controller 才能读取同一个 Session。`public/index.php` 不提前启动 Session；非 HTTP Adapter 必须自行建立 Session 上下文。

Middleware 在启动 Session 前启用 `session.use_strict_mode` 与 `session.use_only_cookies`，并统一设置 Cookie：`HttpOnly=true`、`SameSite=Lax`，HTTPS URI 同时设置 `Secure=true`。已有的 lifetime、path 和 domain 配置会保留；未配置 path 时使用 `/`。Session 空闲 2 小时或持续 12 小时后失效。登录成功和退出登录都会轮换 Session ID 与 CSRF Token，避免认证前后的会话材料继续复用。

登录接口通过 `RateLimiter` 同时限制来源 IP 与“来源 IP + 邮箱”：15 分钟内同一组合最多失败 5 次，同一 IP 最多尝试 30 次，超限返回 `429` 与 `Retry-After`。默认 `FileRateLimiter` 使用文件锁保证单节点并发请求原子计数；多节点部署应提供共享实现。

## SecurityHeadersMiddleware

`SecurityHeadersMiddleware` 位于全局 Pipeline 最外层，为正常响应、短路响应与异常转换后的响应统一设置：

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Content-Security-Policy: base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

当前 CSP 同时限制 Base URL、Object、页面嵌入与表单目标，但仍不限制 Theme 或 Plugin 的脚本、样式和资源来源；未来扩展完整 CSP 前必须先完成现有内联资源迁移和 Theme 兼容性测试。HTTPS 请求额外发送一年期 HSTS；登录、退出、安装和后台路径统一发送 `Cache-Control: no-store` 与 `Pragma: no-cache`。

## AdminLocaleMiddleware

已安装站点的请求在 Session 之后经过 `AdminLocaleMiddleware`。Middleware 从 `admin.locale` 设置解析后台界面语言，并写入显式注入的 `AdminLocale` 请求上下文；Translator 只读取该上下文，不再在每次翻译时查询设置。Locale 必须是安全标识且存在对应后台语言包，否则回退 `zh-CN`。后台界面语言仍与 Content Translation Locale 分离。

## MaintenanceModeMiddleware

`MaintenanceModeMiddleware` 是最外层全局 Middleware，通过 `MaintenanceModeGateway` 读取 Core Update 使用的维护状态。默认文件适配器对应 `storage/maintenance.lock`。锁存在时，普通 HTTP 请求在 Session 启动、路由分发和业务处理前返回 `503 Service Unavailable`，响应包含 `Retry-After: 60` 和 `Cache-Control: no-store`。只读 `GET /health` 是显式白名单，供部署器和负载均衡器判断 PHP 进程仍可响应；同路径写请求不会被放行。锁移除后请求自动恢复，不需要重建 Application。
