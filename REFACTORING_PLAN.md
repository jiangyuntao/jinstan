# Jinstan 现代化架构重构执行计划

> 2026-10-03 决策：Namespace 迁移结束后不再保留上一版类名兼容。旧 `class_alias`、兼容包装器和双 Container binding 已删除；下文较早阶段中的兼容描述仅记录当时的迁移过程，不代表当前可用 API。当前规则见 `docs/architecture/namespace-migration.md`。

## 1. 目的

本文档将 `ARCHITECTURE.md` 转化为可逐步执行、可验证、可回滚的重构任务。

本次重构的核心目标不是一次性重写 Jinstan，而是在持续保持系统可运行的前提下，建立现代 PHP Web Framework 风格的开发体验。

## 2. 执行原则

### 2.1 保持行为稳定

每个阶段必须先识别现有行为，再迁移结构。除当前阶段明确要求外，不同时改变 URL、数据库结构、权限含义、模板输出和 Plugin API。

### 2.2 小步迁移

一次提交应该只完成一个可说明、可测试的架构变化。禁止先移动全部文件再集中修复。

### 2.3 新旧并行

需要改变公共调用方式时，应按以下顺序执行：

```text
建立新接口
→ 添加兼容适配器
→ 迁移 Core 调用方
→ 迁移官方 Plugin / Theme
→ 标记旧接口废弃
→ 在明确版本中删除
```

### 2.4 每阶段可回滚

阶段完成前不得删除仍被调用的旧实现。数据库变更必须具备可验证的回退策略；文件移动必须保持 Composer autoload 和 Core 更新流程有效。

### 2.5 不扩大功能范围

重构阶段不顺带开发电商、支付、复杂工作流、GraphQL、大型 REST API、Multi-site、AI 或可视化 Builder。

## 3. 全程质量门槛

每个阶段至少执行：

```bash
core/vendor/bin/phpunit
core/vendor/bin/php-cs-fixer fix --dry-run --diff
```

如项目提供更细分测试命令，还必须运行当前阶段相关的 Unit、Integration、Feature 和 Architecture 测试。

任何阶段完成时必须满足：

- 首页、后台登录和 `/install` 的既有路径未意外失效。
- Authentication、Permission、CSRF 与输出转义没有弱化。
- SQLite 测试通过。
- 涉及 SQL、Schema 或查询语义时完成 MySQL 和 PostgreSQL 验证。
- Plugin、Theme、Content Type、Field 和 Component 公共边界未被静默破坏。
- 新增自有 PHP 代码符合 strict types 和项目格式规范。
- 对外公共能力有同步文档。

## 4. 重构前基线

### 实施状态

已于 2026-09-13 完成首轮基线产物：

- 完整路由快照：`tests/Architecture/RouteInventoryTest.php`。
- HTTP 与安全冒烟测试：`tests/Feature/CoreHttpBaselineTest.php`。
- 隔离的临时安装站点夹具：`tests/Support/InstalledTestProject.php`。
- 路由基线：`docs/architecture/current-routes.md`。
- Public API 使用基线：`docs/api/public-api-baseline.md`。
- 技术债与测试矩阵：`docs/architecture/technical-debt.md`、`docs/architecture/test-matrix.md`。

本阶段只锁定既有行为，没有迁移生产代码或改变数据库结构。新增测试与格式检查均已通过；截至 2026-09-14，完整测试套件仍有 7 个与本轮结构改动无关的既有错误或失败，因此最终质量门槛尚未关闭。

### 目标

为结构调整建立可比较的行为基线。

### 任务

1. 记录当前所有 Core、Admin、Frontend、Installer 和 Plugin 路由。
2. 为关键页面建立 HTTP Feature Test：
   - `/`
   - `/install`
   - `/login`
   - `/admin`
   - Content 列表、创建、编辑、删除和发布。
3. 建立 Authentication、Permission 和 CSRF 回归测试。
4. 建立 Content、Translation、Taxonomy、Theme Resolution、Component、Plugin 生命周期测试矩阵。
5. 记录当前 Public API 使用位置。
6. 检查代码中直接使用 Container、数据库 Connection 和字符串 URL 的位置。

### 产物

- 路由清单测试。
- 核心请求冒烟测试。
- 公共 API 基线清单。
- 当前架构债务列表。

### 完成标准

主要用户路径具有自动测试保护，后续重构能够判断行为是否发生变化。

## 5. Phase 1：Foundation 与 Application

### 实施状态

已完成。已落地：

- 新增 `Foundation\Application`，管理项目路径、运行环境、Container 和 Provider 生命周期。
- 新增 `ServiceProvider` 契约及 `register()`、`boot()` 顺序保证。
- 新增 `FoundationServiceProvider`，接管 Config 与 Logger 注册。
- 新增 `DatabaseServiceProvider`，统一注册 ConnectionFactory、MigrationRunner 和可选 Connection。
- Installer 与已安装 CMS 共用 Provider 注册的数据库基础设施，不再各自创建连接工厂。
- 新增 `ContentServiceProvider`，接管 Settings、Content Type Registry、Editable Content Types、Content Repository、Taxonomy Repository 和附件存储。
- Bootstrap 与 Admin Controller 装配开始复用 Provider 中的统一 Content/Taxonomy 实例。
- 新增 `ThemeServiceProvider`，接管活动主题解析、Template Catalog、核心 Component、Theme Renderer、前台 Content Controller 与相关 SEO 展示服务。
- `ContentService` 的最终装配已从 Bootstrap 迁出，并继续保留 Template 与 Protected Slug 校验。
- 新增 `AdminServiceProvider`，接管后台认证、Admin Renderer、表单支持、后台 Repository/Service 和除 Plugin 管理外的 Controller 装配。
- Foundation Provider 统一注册 Router 与 CsrfToken，供后续 Provider 复用。
- 新增 `PluginServiceProvider`；Plugin 对象装配位于 `register()`，插件发现、升级和启用注册位于 `boot()`。
- PluginAdminController 与 PluginManager 已从 Bootstrap 迁出，插件路由顺序保持现有快照不变。
- 新增 `InstallerServiceProvider`，Installer 服务不再由 Bootstrap 构造。
- 新增 `RouteServiceProvider`，集中接管全部 Core 路由、Plugin 路由时序、Not Found Handler 和当前 Http Application 装配。
- Bootstrap 已缩减为 Foundation Application 创建、Provider 清单、boot 和 HTTP 处理器返回。
- `public/index.php` 已通过 Foundation Application 的 Container 获取当前 HTTP Application；`Bootstrap::create()` 作为兼容入口继续保留。
- Bootstrap 已通过 Foundation Application 启动，同时保留现有 `Http\Application` 请求行为。
- `APP_ENV` 已明确支持 `local`、`testing`、`production` 等环境名，默认 `production`。
- 已添加 Provider 生命周期与 Foundation 服务集成测试。

### 目标

建立现代框架式启动流程，但暂不迁移具体业务。

### 任务

1. 引入 `Application` 作为应用生命周期入口。
2. 将现有 Bootstrap 缩减为 Composition Root。
3. 定义 Provider Contract：
   - `register()`
   - `boot()`
4. 建立统一配置加载流程。
5. 明确开发、测试和生产环境。
6. 保留现有 Container 兼容层，禁止业务代码新增 Service Locator 用法。
7. 添加 Application 生命周期测试。

### 建议提交顺序

1. Provider Contract 与测试。
2. Application 生命周期与测试。
3. Bootstrap 通过 Application 启动。
4. 将基础服务注册迁入 Foundation Provider。

### 完成标准

- `public/index.php` 只负责创建并运行 Application。
- Bootstrap 不再直接承载所有业务路由和对象构造。
- 原有 HTTP 行为保持不变。

## 6. Phase 2：HTTP Kernel 与异常处理

### 实施状态

已完成。已落地：

- 新增统一 `Http\Kernel`，所有现有 Web 请求已通过 Kernel。
- 新增 Middleware Contract、Request Handler Contract 与 Pipeline。
- 后台 POST 成功跳转反馈逻辑已从旧 HTTP Application 抽成 Middleware。
- 新增统一 Exception Handler；未知异常会记录请求方法和路径，生产环境隐藏异常细节。
- 已定义 Authentication、Authorization、CSRF、Validation 与 Not Found 五类 HTTP 异常。
- Middleware 顺序、短路、已知异常和未知异常行为测试已通过。
- 新增统一 Response Factory，支持 HTML、Text、JSON、Redirect 与文件响应，并已注册到 Container。
- Exception Handler 已根据 `Accept: application/json` 输出统一 JSON 错误，Validation 异常包含结构化字段错误。
- 新增后台安全 Middleware，统一处理 `/admin` 访客跳转、JSON 401 和非只读请求 CSRF 校验。
- Login 与 Language Admin Controller 已迁移到注入式 Response Factory，不再直接依赖具体 PSR-7 Response 实现。
- 新增共享 `AdminAccess` 授权协调服务；Language 与 Plugin Admin Controller 已删除各自重复的 `authorized()` 方法。
- Plugin Admin Controller 已同步迁移到 Response Factory。
- Cache/Logs 与 Role Admin Controller 已迁移到 AdminAccess 和 Response Factory，并删除重复授权方法。
- Database Tools 与 Theme Admin Controller 已迁移到 AdminAccess 和 Response Factory，数据库维护、备份和主题文件操作顺序保持不变。
- Navigation 与 Taxonomy Admin Controller 已迁移到 AdminAccess 和 Response Factory，多语言、层级、排序及删除保护逻辑保持不变。
- Content Type Admin Controller 已迁移到 AdminAccess 和 Response Factory，动态字段、模板、路由冲突与删除边界保持不变。
- Content Admin Controller 已迁移到 AdminAccess 和 Response Factory；动态表单、附件、多语言、Taxonomy、发布状态与 HTML 权限检查保持不变。
- User 与 Settings Admin Controller 已迁移到 AdminAccess 和 Response Factory；个人资料使用 login-only 访问规则，用户管理与站点设置仍使用各自明确权限。
- Dashboard 与 Media Admin Controller 已迁移到 AdminAccess 和 Response Factory；至此所有实际 Admin 路由使用的 Controller 均已接入新响应体系。
- Installer、Frontend Content、Public Media、Sitemap 与兼容重定向路由已迁移到 Response Factory。
- Plugin 路由回调已改用 PSR ResponseInterface，并通过 PluginRegistrar 提供 Core Response Factory。
- 新增架构测试，确保除 Response Factory 外的 Core 类不依赖具体 PSR-7 Response 实现。

后续衔接：Phase 3 支持路由 Middleware Group 后接入统一 Permission Middleware，并逐步将 Controller 兼容授权响应迁移为标准 HTTP 异常。

### 目标

形成统一 Request → Response 管线。

### 任务

1. 建立 `Http\Kernel`。
2. 建立 Middleware Contract 和 Pipeline。
3. 建立统一 Exception Handler。
4. 定义常用 HTTP 异常：
   - AuthenticationException。
   - AuthorizationException。
   - CsrfTokenMismatchException。
   - ValidationException。
   - NotFoundException。
5. 统一 HTML、Redirect、JSON 和文件 Response 创建方式。
6. 把当前顶层错误处理迁入 Exception Handler。

### 测试

- Middleware 顺序。
- Middleware 短路响应。
- 已知异常状态码。
- 未知异常日志与生产环境隐藏细节。

### 完成标准

所有 Web 请求经由同一个 Kernel，Controller 不再自行实现全局错误处理。

## 7. Phase 3：现代路由系统

### 实施状态

进行中。已完成：

- Router 注册现在返回 Route 对象，原有不接收返回值的 `get()`、`post()` 调用保持兼容。
- Route 支持唯一名称，重复名称会被拒绝，重命名会移除旧名称映射。
- 新增 UrlGenerator，支持必填路径参数、正则参数占位符、URL 编码和额外 Query 参数。
- Foundation Provider 已注册 Router 与 UrlGenerator 共享实例。
- 安装、登录、后台首页、站点首页和 Sitemap 已设置首批稳定路由名。
- Router 支持可嵌套 Prefix Group，组内根路径会正确解析为组前缀本身。
- Route 与 Route Group 支持 Middleware；组 Middleware 按嵌套顺序合并，路由 Middleware 可在 Controller 前短路响应。
- 全局 Kernel Middleware 继续位于最外层，路由 Middleware 只在路由匹配成功后执行。
- Router 支持 `[Controller::class, 'method']` Action，并通过 PSR Container 延迟解析 Controller 实例。
- 新增受控 Route Registrar，仅暴露路由注册、分组和 Route 配置能力，不向 Plugin 暴露 dispatch 与路由表查询。
- PluginRegistrar 通过 `routes()` 提供受控的分组注册 API。
- 新增 `core/routes/install.php`，安装状态健康检查与 Installer GET/POST 路由已从 RouteServiceProvider 迁出。
- Installer 路由文件使用 Route Registrar 和 Controller 类名 Action，未安装环境仍只开放既有安装入口。
- 新增 `core/routes/auth.php`，登录页面与登录提交路由已从 RouteServiceProvider 迁出并改用 Controller 类名 Action。
- 新增 `core/routes/admin.php`，Plugin 之前注册的后台 Media、Dashboard、Content、Content Type、Taxonomy 与 Navigation 路由已迁入 `before_plugins` 分区。
- 首批 Admin 路由统一使用 `/admin` Prefix Group 与 Controller 类名 Action，既有路由顺序保持不变。
- Admin 的 Plugin、Theme、Database、User、Role、Profile、Settings、Language、Cache、Logs 与兼容重定向已迁入 `after_plugins` 分区。
- Logout 已由 Admin 路由文件接管但保持 `/logout` 原路径；动态 Content 路由之后的 `/admin/system/cache` 由 `after_content` 分区保持原优先级。
- RouteServiceProvider 不再直接声明具体 Admin URL，仅协调 Admin 分区与 Plugin、动态 Content 路由的注册时序。
- 新增 `core/routes/web.php`，首页、Locale 首页、Sitemap、公开 Media 与动态 Content Type 路由已全部迁出 RouteServiceProvider。
- Web 路由按 `before_auth`、`after_auth`、`content` 分区保持现有优先级；ContentRoutes 已改为依赖受控 Route Registrar。
- Route Registrar 新增只读 `has()` 冲突检查能力，动态 Content 路由无需访问完整 Router。
- Route Registrar 新增受验证的 `load()`，Core 与 Plugin 路由文件统一要求 PHP 文件返回注册 Closure。
- HelloPlugin 与 ExternalOrderPlugin 的路由已迁入各自 `routes/web.php`，Plugin 主类只负责服务/Component 装配和加载路由文件。
- Plugin 路由继续在 PluginManager boot 时按既有发现顺序注册，不改变 Core Admin 前后分区优先级。
- Admin 稳定入口已补充首批语义化路由名，覆盖 Content Type、Plugin、Theme、Database、User、Role、Profile、Settings、Language、Cache、Logs 与 Logout。
- 路由文件中的旧系统跳转已改用 UrlGenerator，Query String 与 Fragment 继续保持原输出。
- AdminViewRenderer 已注册 Latte `route()` Helper，生产环境使用 Container 中的 UrlGenerator 生成路径。
- 后台基础 Layout 的 Dashboard、设置、工具、账户、Profile、站点首页与 Logout 链接已迁移为命名路由。
- User、Role、Language、Plugin、Database、Profile、Cache、Logs 等业务路由已补充首批 CRUD 路由名。
- Login、User、Role 固定入口、Plugin Settings、Dashboard、Profile、Database 操作、Cache 与 Logs View 已开始使用 Latte `route()` Helper。
- Role、Language、Plugin、Database 的参数化编辑、删除、切换、备份与分页 URL 已全部迁移到命名路由。
- Login、User、Role、Language、Plugin、Database、Profile、Cache、Logs View 已不再包含对应业务路径的硬编码链接或表单 Action。
- Content 与 Content Type 已补齐 index/create/store/edit/update/delete 及搜索路由名，并迁移列表、表单取消、导航入口和内容类型行操作。
- AdminContentForm 展示数据新增 `typeHandle`，Content Form 可通过命名路由返回正确类型列表，不再退回无效的 `/admin/content`。
- Content 与 Content Type View 已不再包含对应业务路径的硬编码链接或表单 Action；Controller/表单服务准备的动态 URL 保留到后续服务层迁移。
- Taxonomy 与 Term 路由已拆分为明确的 index/create/store/edit/update/delete、删除确认和 reorder 名称。
- Navigation 与 Navigation Item 已补齐 index/store/create/edit/update/delete/reorder 路由名。
- Taxonomy、Navigation、Content Type 的关联入口和后台侧栏已迁移到 Latte `route()` Helper，对应 View 不再硬编码业务路径。
- Theme 工作区已补齐保存、创建、目录与文件操作路由名；主题和文件选择的 Query 参数统一由 UrlGenerator 编码。
- Theme View 已不再硬编码 `/admin/themes` 链接或表单 Action。
- Content Admin Controller 与 AdminContentForm 已接入 UrlGenerator，统一生成列表操作、表单提交、删除、保存跳转、父内容搜索、Taxonomy 搜索和分页 URL。
- Taxonomy 与 Navigation Admin Controller 已接入 UrlGenerator，列表上下文、分页及写操作后的返回 URL 不再由生产代码拼接。
- Theme 与 Plugin Admin Controller 已接入 UrlGenerator，主题/文件上下文和插件设置反馈 Query 参数由统一生成器处理。
- User、Role、Language 与 Settings Admin Controller 已接入 UrlGenerator；系统设置保存路由已命名，相关表单 Action 和操作完成后的跳转不再依赖生产代码中的固定路径。
- Database Tools、Content Type、Cache/Logs 与 AdminAccess 已接入 UrlGenerator；遗留 Reading Settings、Cache/Logs View 的固定 Action 已清理。
- Dashboard、Login、Admin Security Middleware 与 Installer 表单已接入 UrlGenerator；后台媒体预览路由已命名并由 AdminContentForm 参数化生成。
- 路径前缀识别、Protected Slug 和安装完成后的根路径属于 HTTP 协议边界，继续使用明确路径常量，不作为页面 URL 拼接。
- 新增 TestUrlGenerator 测试辅助设施；Login Controller 与 Admin Security Middleware 已将 UrlGenerator 收紧为必需依赖并删除路径回退。
- Installer 与 AdminContentForm 已将 UrlGenerator 收紧为必需依赖；Content 表单操作、搜索与媒体预览的字符串回退已删除，相关测试改用真实命名路由。
- Content Admin Controller 与 Dashboard 已将 UrlGenerator 收紧为必需依赖；Dashboard 同时移除内置 Content Type URL 回退，统一依赖 Registry。
- Taxonomy、Navigation、Theme 与 Plugin Admin Controller 已将 UrlGenerator 收紧为必需依赖并删除路径回退；Theme SettingsRepository 同步收紧为必需依赖。
- User、Role、Language、Settings、Database Tools 与 Cache/Logs Controller 已将 UrlGenerator 收紧为必需依赖，并在内部授权协调器中复用同一实例。
- AdminAccess、Content Type、Reading Settings、Media 与 System Info 的路由依赖已收紧；所有后台授权构造路径均显式提供 UrlGenerator。
- AdminViewRenderer 已将 UrlGenerator 收紧为必需依赖并删除 `#` 占位回退；模板测试使用完整命名路由表，缺少路由参数会直接失败。
- 修复 Content Form 测试 View Model 缺少 `typeHandle` 的问题，该缺陷此前被模板路由回退掩盖。

Phase 3 已完成：Core 的 View、Redirect、Controller 与 Middleware 均通过命名路由生成业务 URL，迁移期可选 UrlGenerator 和路径回退已清理。

### 目标

把路由从集中式 Bootstrap 中移出，并提供主流框架式注册体验。

### 任务

1. 支持 Route Name。
2. 支持 Prefix Group。
3. 支持 Middleware Group。
4. 支持 Controller Action。
5. 建立 URL Generator。
6. 创建：
   - `core/routes/web.php`
   - `core/routes/admin.php`
   - `core/routes/install.php`
7. 将现有路由逐组迁移。
8. 为 Plugin 提供受控 Route Registrar。

### 迁移顺序

1. Installer。
2. Authentication。
3. Admin 只读页。
4. Admin 写操作。
5. Frontend Content 与 Sitemap。
6. Plugin Routes。

### 完成标准

- Bootstrap 中不再出现具体业务 URL。
- View 和 Redirect 可以通过 Route Name 生成 URL。
- 现有 URL 保持兼容。

## 8. Phase 4：安全 Middleware

进行中。已完成：

- 新增 `RequirePermissionMiddleware`，使用明确 Permission Key，在无权限时返回 403。
- Content Type 路由组已声明 `content.manage` Middleware，现有 AdminSecurityMiddleware 继续在外层保证登录与写请求 CSRF。
- ContentTypeAdminController 已删除重复 Permission 检查及 Authenticator/AdminAccess 依赖，资源级删除保护与输入校验保持不变。
- 新增权限允许/拒绝单元测试，并保留现有路由与 Content Type 回归覆盖。
- 新增 `AuthenticateMiddleware`，分别处理浏览器登录跳转和 JSON 401 异常。
- 所有 Core Admin 路由组已显式声明 AuthenticateMiddleware；全局 AdminSecurityMiddleware 暂时为 Plugin Admin 路由保留认证兜底。
- 路由集成测试验证 Dashboard 包含认证 Middleware，Content Type 路由按 Authenticate → RequirePermission 顺序执行。
- 新增 `VerifyCsrfTokenMiddleware`，支持安全读取 Method、表单 Token 与 `X-CSRF-Token` Header，失败统一抛出 CSRF 异常。
- 所有 Core Admin 路由组已声明 VerifyCsrfTokenMiddleware；ContentTypeAdminController 已删除重复 CSRF 分支。
- 路由集成测试更新为 Authenticate → VerifyCsrfToken → RequirePermission 的明确执行顺序。
- Cache/Logs 路由组已迁移到 Authenticate → VerifyCsrfToken → `system.manage`；Controller 删除重复登录、权限和 CSRF 判断，HTTP 集成测试覆盖拒绝与合法操作。
- Role 路由组已迁移到 Authenticate → VerifyCsrfToken → `users.manage`；Controller 只保留表单 Token 输出和角色业务规则，HTTP 集成测试覆盖访客、无权限、缺失 Token 与合法创建。
- User 管理路由已迁移到 Authenticate → VerifyCsrfToken → `users.manage`；个人资料继续保持仅登录边界，Controller 保留当前用户自我降权保护与用户业务校验。
- Language 路由组已迁移到 Authenticate → VerifyCsrfToken → `settings.manage`；Controller 保留 Locale 白名单、默认语言删除保护与多语言设置持久化规则。
- System Settings 与旧 Reading Settings 兼容入口已并入 `settings.manage` 路由组；Controller 删除重复请求级安全判断，保留主题、Locale、SEO、URL 与预定义页面校验。
- Plugin 管理路由已迁移到 Authenticate → VerifyCsrfToken → `settings.manage`；Controller 保留 Plugin 生命周期、设置定义校验与卸载行为，HTTP 测试覆盖合法停用。
- Theme 管理路由已迁移到 Authenticate → VerifyCsrfToken → `system.manage`；路径边界、文件类型、活动主题保护和明确覆盖/删除确认继续由 Theme 业务边界执行。
- Database Tools 路由已迁移到 Authenticate → VerifyCsrfToken → `system.manage`；数据库驱动差异、完整性检查与备份文件边界继续由数据库和备份服务处理。
- Navigation 路由已迁移到 Authenticate → VerifyCsrfToken → `content.manage`；菜单层级、Locale fallback、排序集合完整性和链接字段规则继续由 Navigation 业务边界处理。
- Taxonomy 与 Term 路由已迁移到 Authenticate → VerifyCsrfToken → `content.manage`；删除确认、Content Type 引用保护、层级、Locale 翻译和分页内排序规则保持不变。
- 后台受保护 Media 路由已迁移到 Authenticate → VerifyCsrfToken → `content.manage`；存储路径格式、哈希目录、扩展名和 MIME 响应边界保持不变。
- Content CRUD、父内容与 Taxonomy 搜索路由已迁移到 Authenticate → VerifyCsrfToken → `content.manage`；完整 HTML 继续额外要求 `system.manage`，动态 Field、附件回滚和发布规则保持不变。
- Dashboard 已完全依赖路由级 Authenticate 与 VerifyCsrfToken，不再在 Controller 中重复判断登录；Dashboard 继续保持“仅登录、不额外要求管理权限”的既有边界。
- 新增 EnsureNotInstalledMiddleware，并应用于 GET/POST `/install`；Installation Lock 检查已从 InstallController 移至路由层，根健康检查不受影响。
- 新增全局 StartSessionMiddleware，并从 public/index.php 移除提前 session_start；Session 在 Router、认证和 CSRF 之前启动，Authenticator 保留直接调用兼容启动。
- 新增 AdminLocale 请求上下文与全局 AdminLocaleMiddleware；后台 Locale 在每次请求开始时从设置解析，缺失或非法语言包安全回退 zh-CN，Translator 不再直接查询设置。
- 新增全局 MaintenanceModeMiddleware，复用 Core Update 的 maintenance.lock；维护期在 Session、Router 和 Controller 之前统一返回 503，并设置 Retry-After 与 no-store。独立 GET /health 作为严格只读白名单，首页和后台不放行。

### 目标

消除 Controller 中重复的安全模板代码。

### 任务

1. 实现 InstallState Middleware。
2. 实现 Session Middleware。
3. 实现 Authenticate Middleware。
4. 实现 RequirePermission Middleware。
5. 实现 VerifyCsrfToken Middleware。
6. 实现 Admin Locale Middleware。
7. 实现 Maintenance Mode Middleware。
8. 路由分组声明 Middleware。
9. 保留资源级 Policy 判断。

### 测试

- 未登录访问。
- 无权限访问。
- 缺失和错误 CSRF。
- 合法写操作。
- Installer 锁。
- Maintenance Mode 白名单。

### 完成标准

Controller 不再重复基础 Authentication、Permission 和 CSRF 判断，安全测试覆盖不低于重构前。

## 9. Phase 5：Request Validation

进行中。已完成：

- 新增 Validator Contract、不可变 ValidationResult 与 ActionRequest 基类，验证失败统一抛出带字段错误的 ValidationException。
- Login 已迁移到 LoginRequest，集中处理安全字符串提取、邮箱 normalization、必填和格式校验；密码不做 trim，CSRF 仍先于表单校验。
- Login HTTP 集成测试覆盖 JSON 422 的稳定字段错误结构。
- Role 已迁移到 RoleRequest：角色标识、名称和权限列表的 normalization 与基础格式校验从 Controller 移出；权限白名单、内置角色和标识不可变仍由 RoleRepository 守卫。非法表单保留 normalization 后的值回显，不执行写操作。
- User 管理和个人资料已迁移到 UserRequest：创建时密码必填，更新时可留空，邮箱、密码长度和角色列表格式由 Request 校验。角色存在性、最后管理员与当前用户自我降权保护仍保持在原业务边界。个人资料验证失败现可稳定回显 422，不再将未校验输入直接写入 Repository。
- System Settings 已开始迁移到 SystemSettingsRequest：站点基本文本、联系邮箱、前台启用 Locale、后台 Locale、Theme、预定义 Homepage/404 页面、保留路径、时区、日期格式、分页、排序、默认内容状态和表单活动 Tab 已从 Controller 提取。日期格式、后台 Locale、预定义页面定义与 Tab 白名单由 Request 的共享常量同时供页面和保存逻辑使用，前台 Locale 从已启用 Content Locale 解析。Theme 选项扫描和首页模板可用性也由 Request 统一提供，Controller 不再直接读 Theme 目录。预定义页面 ID 必须指向 Page Content，异常类型不会被强制转换。保留路径由 Request 逐行转小写、去尾斜杠、格式校验并去重。部分提交继续从 SettingsRepository 读取已保存的 Content 默认值，不会意外重置。
- SEO robots、默认标题/描述、标题分隔符与四类标题模板已迁移到 SystemSettingsRequest。Request 校验标题模板长度、占位符白名单和花括号语法，同时保留分隔符前后空格。
- SNS 地址已迁移到 SystemSettingsRequest：复用 SnsPlatforms 清单，未提交的平台沿用已保存值；提交值必须是字符串且为空或完整 HTTP/HTTPS URL，数组等异常输入返回 422。
- 自定义设置键值对已迁移到 SystemSettingsRequest：最多 100 项，键名规范化、格式校验与去重后按键排序；值最长 10000 字符。异常嵌套输入返回 422，验证通过后才执行整组替换。
- System Settings 的普通设置与自定义设置现由 SettingsRepository::saveSystem() 在同一数据库事务中提交；后半段失败时前半段及自定义设置删除均回滚，SQLite 失败注入集成测试覆盖该边界。
- Content 创建和更新已接入 ContentRequest，先验证固定字段和动态字段容器的请求形状，再处理附件与业务保存；动态 Field 值仍交给 Field Type，Taxonomy 与模板规则仍留在原服务边界。
- ContentRequest 已继续覆盖提交语言和发布状态：明确拒绝未启用 Locale、异常布尔输入与未知状态值，避免隐式回退造成写错翻译或发布状态。
- ContentRequest 已校验媒体删除、管理与排序标记的外层结构，阻止非法排序输入被默默当作空列表而移除现有附件引用；具体附件归属仍由 Content 附件逻辑处理。
- 媒体上传输入已抽到 ContentUploadRequest，统一处理空上传槽、上传错误、异常嵌套值与单文件数量限制；文件大小、MIME、扩展名及存储位置继续由 ContentAttachmentStorage 验证。

### 目标

统一输入提取、normalization、校验和错误返回。

### 任务

1. 定义 Validator Contract 和 Validation Result。
2. 建立基础 Form Request 或 Action Request。
3. 先迁移固定结构表单：
   - Login。
   - Settings。
   - User。
   - Role。
4. 再迁移动态 Content 表单。
5. 保留 Field Type 的 normalization、validation、serialization 和 deserialization 权责。
6. 建立统一上传验证入口。

### 完成标准

- Controller 只接收可信的已验证输入。
- 表单错误能够稳定回显。
- 动态 Field 不需要 Controller 分支。

## 10. Phase 6：拆分 ContentAdminController

进行中：已提取 `ContentListQuery`，接管 Content 列表的翻译回退、可见列搜索、状态过滤和分页；无关键词路径已由 `ContentRepository::adminPage()` 在数据库完成状态过滤、计数和分页。关键词搜索暂保留 PHP 筛选以维持多语言回退与可见列语义。`ContentListPresenter` 现接管可见列、翻译来源、行操作和分页 URL 的展示数据，列表 Controller 不再遍历数据库结果或拼装行数据。

`ContentFormPresenter` 已接管表单 Field、模板、Taxonomy、父内容与源语言参考数据准备，Controller 的 `formPage()` 只协调类型读取、Presenter 和 HTML Response；上传及保存逻辑暂未迁移。

上传协调前置修复：创建/更新过程中若完整 HTML 权限检查返回 403，或多文件上传在后续文件失败，Controller 现仍保留已创建路径并清理未被引用的附件。回归测试覆盖创建、更新拒绝及中途上传失败；后续再提取独立上传服务。

`ContentAttachmentCoordinator` 已提取上传合并、替换路径、内容删除附件收集与跨内容引用检查。Controller 不再直接遍历媒体 Field 或文件存储；共享哈希附件在最后一条引用删除前保持可用，并有独立回归测试。

`CreateContent` 用例已接管创建时的附件准备、完整 HTML 权限检查、内容与分类保存、失败附件清理。Controller 仅做请求验证与 403/422/跳转响应；成功创建及重复 Slug 回滚清理有集成测试。更新和删除用例留待后续迁移。

`UpdateContent` 用例已接管更新时的现有数据合并、附件替换、完整 HTML 权限检查、事务保存及成功/失败后的附件清理。回归测试验证成功更新后旧附件删除，以及重复 Slug 失败时旧内容与附件保留、新附件清理。删除用例留待后续迁移。

`DeleteContent` 用例已接管删除时的类型匹配、附件路径收集、数据库事务与提交后的无引用附件清理。`ContentRepository::transaction()` 支持在已有事务内复用连接；SQLite 失败注入测试验证最后一步删除失败时翻译回滚且附件仍在。Controller 仅保留 404 与成功跳转响应。

`ContentSearchQuery` 已接管父内容与 Taxonomy 联想搜索、Content Type 层级及绑定检查；Controller 仅提取查询参数、映射 404 并返回 JSON。独立测试覆盖类型边界、排除自身与已绑定分组搜索。

`ContentEditQuery` 已接管编辑表单的 Content/Translation 读取与共享/当前语言字段合并；不存在或类型不匹配由 Controller 映射 404，缺失当前翻译时仍只显示空标题与源语言参考，不误写回退语言内容。

列表参数提取已收紧：搜索词、状态、页码和可见列遇到嵌套数组时不会强制转换或触发警告，异常值安全回退到空筛选、第一页和必需列；正常分页与列选择保持原行为。

AdminServiceProvider 已统一注册并注入 Content Query、Presenter、附件协调器及创建／更新／删除用例；ContentAdminController 的 Action 不再直接 `new` 应用协作者。构造器末尾保留兼容默认装配，以维持现有独立 Controller 测试和低层嵌入方式。

### 目标

以最大 Controller 为样板，建立标准 MVC 与 Application Layer 协作方式。

### 目标调用链

```text
ContentAdminController
├── ContentListQuery
├── ContentFormPresenter
├── CreateContent / UpdateContent
├── DeleteContent
└── AdminViewRenderer
```

### 任务

1. 提取 `ContentListQuery`：搜索、状态过滤、分页和 Locale 读取策略。
2. 将可下推的过滤和分页移入 Repository 查询，避免 Controller 加载全部记录。
3. 提取 `ContentListPresenter`：列、URL、翻译状态与分页展示数据。
4. 提取 `ContentFormPresenter`：页签、字段定义、模板选项和 Taxonomy 展示数据。
5. 提取上传协调服务，明确新文件和替换文件的回滚行为。
6. 将创建、更新、发布和删除变成明确 Application 用例。
7. 由 Application Service 管理事务。
8. 缩减 Controller Action，使其只协调请求、用例和响应。

### 测试

- 列表过滤、分页和列选择。
- Locale fallback。
- 创建与更新事务。
- 上传失败清理。
- 删除附件边界。
- HTML 权限限制。
- Parent Content 与 Taxonomy。

### 完成标准

- Controller 不包含数据库遍历和展示数组拼装。
- Content 业务行为保持不变。
- Article、Page、Product 仍共用统一 API。

## 11. Phase 7：迁移其他 Admin Controller

已完成：Phase 6 已通过完成标准复核，ContentAdminController 不再包含数据库遍历、事务或展示数组拼装，三种默认 Content Type 继续共用统一 API。Phase 7 已从 Settings 开始，`SystemSettingsPresenter` 接管系统设置页面的页面选项、默认值、语言／主题／SNS／保留路由及活动 Tab View Model；`SaveSystemSettings` 接管已验证设置的键映射、请求 Origin 解析和事务提交，Controller 只保留 HTTP 协调。

Taxonomy 迁移已完成：`TaxonomyIndexQuery` 与 `TaxonomyTermListQuery` 接管顶层清单、条目分页和缺失翻译补充，`TaxonomyListPresenter` 接管列表 View Model；`TaxonomyTranslationResolver` 统一列表与表单的来源翻译选择和 Locale 名称解析。`ReorderTaxonomyTerms` 接管分页内排序输入校验和写入协调，`SubmitTaxonomyForm` 接管 Taxonomy／Term 的创建、更新、删除确认与 Repository 调用；`TaxonomyFormQuery` 和 `TaxonomyFormPresenter` 分别接管表单读取与 View Model。Controller 不再持有 Repository，不再直接执行查询、写操作或拼装列表／表单展示数组，仅保留 HTTP 输入协调、异常响应与重定向。下一步迁移 Navigation。

Navigation 迁移已完成：`NavigationListQuery` 接管菜单清单、菜单项和 Locale 查询，`NavigationListPresenter` 接管列表 View Model；`NavigationTranslationResolver` 统一列表与编辑表单的来源翻译选择，`NavigationItemFormQuery` 接管菜单项、原文参考和父级候选 View Model；`NavigationWriter` 接管菜单与菜单项的创建、更新、删除、输入规范化和完整集合排序。Controller 不再持有 `NavigationRepository`，不再直接查询、写入或拼装大段展示数据，仅协调 HTTP 响应、重定向和安全的异常转换。下一步迁移 Theme。

Theme 迁移已完成：`ThemeManagerQuery` 接管主题清单、文件选择、目录树、源码、活动主题和删除资格 View Model；`ThemeWriter` 接管覆盖确认、主题及目录／文件的创建、重命名和删除，并返回统一重定向上下文；`ThemeFileManager` 作为文件系统基础设施由 Provider 显式注册并注入。Controller 不再持有文件管理器或 Settings Repository，不再直接执行文件系统操作，仅协调输入、404／422 响应和命名路由重定向。下一步迁移 User 与 Role。

User 与 Role 迁移已完成：`UserWriter` 接管用户角色白名单、创建、更新、删除、自身管理员角色保护和个人资料更新；`UserAdminQuery` 与 `UserAdminPresenter` 接管用户读取及列表、表单、个人资料 View Model。`RoleWriter` 接管角色保存和删除，`RoleAdminQuery` 与 `RoleAdminPresenter` 接管角色、权限读取及 View Model。两个 Controller 均不再持有领域 Repository，不再直接查询、写入或拼装展示数组；密码最小 6 位规则继续由 `UserRequest` 负责。下一步迁移 Plugin。

Plugin 迁移已完成：`PluginAdminQuery` 接管插件清单及可配置插件读取，`PluginAdminPresenter` 接管插件列表和设置表单 View Model；`PluginWriter` 接管启停意图、设置提交和卸载协调，`PluginManager` 继续执行生命周期及 Manifest 驱动的设置规则。Controller 不再持有或直接调用 Plugin Manager，仅协调 HTTP 输入、404／422 响应与命名路由重定向；嵌套设置值不会被强制转换为字符串。下一步迁移 Database Tools。

Database Tools 迁移已完成：`DatabaseToolsQuery` 接管驱动、Migration 数量、完整性检查和备份分页，`DatabaseToolsPresenter` 接管跨数据库说明及页面 View Model；`DatabaseMaintenance` 接管 Driver-specific 优化、备份创建和备份删除。Controller 不再持有或直接访问 Connection、BackupService，不再拼装展示数组，仅协调 HTTP 响应和命名路由重定向；异常分页参数安全回退第一页。下一步迁移 Cache、Logs 与 System Info。

Cache 与 Logs 迁移已完成：`CacheLogsQuery` 接管缓存目录占用统计和日志尾部读取，`CacheLogsPresenter` 接管本地化名称、容量标签、CSRF 与页面 View Model，`CacheLogsMaintenance` 接管缓存和日志清理并保留 `.gitkeep` 目录。Controller 不再遍历文件系统或拼装展示数组，仅协调页面响应和命名路由重定向。未注册、无路由且功能已由 Dashboard 与 Database Tools 覆盖的 `SystemInfoController` 及其孤立 View 已移除，不新增重复后台入口。下一步迁移 Dashboard。

Dashboard 迁移已完成：`DashboardQuery` 接管 Content Type 数量、发布状态、运行环境、依赖版本及数据库状态读取，`DashboardPresenter` 接管默认类型名称本地化、命名路由链接、数据库说明和安全图表 JSON。Controller 不再持有或直接访问 Content Repository、Content Type Registry、Connection 和 UrlGenerator，仅协调 Query、Presenter 与 HTML Response。Phase 7 的既定 Controller 迁移清单完成。未注册的 `ReadingSettingsController` 及孤立 View 已移除，兼容 GET／POST 路由继续重定向至系统设置的“预定义页面”Tab；兼容构造所需的 Repository 参数仅用于创建默认 Application Service，不再作为 Settings Controller 状态持有。Phase 7 完成标准复核通过，下一步进入 Phase 8。

### 目标

将 Content 模块形成的模式应用到其余后台。

### 迁移顺序

1. Settings。
2. Taxonomy。
3. Navigation。
4. Theme。
5. User 与 Role。
6. Plugin。
7. Database Tools。
8. Cache、Logs 与 System Info。
9. Dashboard。

### 每个 Controller 的固定步骤

1. 补齐 Feature Test。
2. 识别查询、命令、Presenter 和基础设施操作。
3. 提取 Application Service。
4. 提取 Presenter / View Model。
5. 迁移安全检查到 Middleware 或 Policy。
6. 缩减 Controller。
7. 删除确认无调用的旧私有逻辑。

### 完成标准

Admin Controller 均符合 `ARCHITECTURE.md` 的 MVC 约束，没有直接 SQL、复杂事务和大段 View 数据装配。

## 12. Phase 8：Frontend、Installer 与 SEO Controller

已完成：Sitemap 已完成首个迁移切片。`SitemapQuery` 接管已发布 Content、启用 Locale、Content Type 列表能力和 URL 路径集合查询，`SitemapPresenter` 接管 XML 编码；Controller 不再执行 SQL、筛选路径或拼接 XML，只协调 Query、Presenter 与 XML Response。现有 `/sitemap.xml` 路由和响应头保持不变。

Public Content 首页链路已迁移：`PublicHomepageQuery` 接管 Locale 校验、预定义首页解析、翻译回退、内容分区、排序和 URL／canonical 构建，`PublicContentPresenter` 接管 ThemeRenderer 调用；Controller 的首页 Action 仅协调 Query、404 分支和 HTML Response。详情、列表与 404 链路继续按小切片迁移。

Public Content 详情与列表链路已迁移：`PublicContentQuery` 接管 slug／Locale fallback、历史路径重定向、hreflang、列表分页与排序、前后页链接及 canonical，`PublicContentPresenter` 接管详情和列表 ThemeRenderer 调用；Controller 只协调 404、301 和 HTML Response。下一步迁移自定义 404 链路。

Public Content 自定义 404 链路已迁移：`PublicNotFoundQuery` 接管请求路径 Locale 解析、预定义 Page 校验、翻译 fallback、canonical 和 hreflang，Presenter 接管 `not_found` Theme 语境渲染。Controller 不再持有 Repository、Settings、ThemeRenderer、RedirectRepository、SchemaRegistry 或 Content Type Registry，仅协调 Query／Presenter 与 404 Response；纯文本与自定义 404 的状态码和 noindex 行为保持不变。

Installer 已完成步骤化迁移：`InstallationInput` 接管站点、管理员和数据库输入校验，`InstallationSeeder` 接管初始用户、角色及设置事务，`DatabaseConfigWriter` 隔离数据库配置文件写入，`InstallApplicationService` 按 Lock 检查、连接探测、Migration、Seed、配置写入和 Lock 创建顺序协调安装。Controller 不再调用聚合实现，只协调 CSRF、环境检查、Application Service、错误响应和跳转；`Installer` 作为旧调用方兼容入口继续委托同一服务。

Public Media 已完成边界收紧：`PublicMediaFileReader` 接管路由片段组合、存储路径白名单校验和附件读取，Controller 不再直接调用通用附件存储或构造文件路径，仅返回带 MIME、长期不可变缓存和 `nosniff` 的文件响应或 404。非法路径、目录穿越和缺失文件均有集成测试保护。

Installer 展示边界已收紧：`InstallPresenter` 接管环境检查列表、命名路由表单 Action、CSRF 字段及转义后的错误页面输出，Controller 不再拼接 HTML。至此 Phase 8 的 Public Content Query／Presenter、Public Media 文件读取、步骤化 Installer 和 Sitemap Query／XML Presenter 均已落地。控制器扫描与完整回归通过，Locale、canonical、hreflang、SEO、安装和文件响应行为保持稳定，Phase 8 完成标准复核通过；下一步进入 Phase 9。

### 目标

统一所有 HTTP Controller 的开发方式。

### 任务

1. 将 Public Content 查询提取为 Query Service。
2. 将 Theme View 数据构建提取为 Presenter。
3. 将 Media 路径验证和文件读取隔离到文件存储服务。
4. 将 Installer 流程拆成步骤化 Application Service。
5. 将 Sitemap 数据查询与 XML Response 分离。
6. 确保前台 Locale、canonical、hreflang 和 SEO 输出不回退。

### 完成标准

Admin、Frontend、Installer 和 SEO 使用统一的 Kernel、Middleware、Controller 和 Response 约定。

## 13. Phase 9：Repository Contract 与 Persistence Adapter

进行中：新增最小 Public API `ContentReadRepository`，仅定义 find、list、translation(s)、publishedPage 和 findBySlug 等稳定只读能力。现有数据库 `ContentRepository` 实现该契约，Provider 将同一实例同时绑定到接口和兼容具体类；Public Homepage、Content Detail／Listing、Not Found 与 Dashboard Query 已迁移为依赖契约，不再依赖具体数据库 Repository。写入、事务、后台搜索和分页暂不并入接口，避免形成大而全契约。

Content 写入契约已拆分：`ContentWriteRepository` 仅定义 create、update、saveTranslation、setPublished、delete 和 transaction，`ContentRepositoryContract` 组合稳定读写能力。ContentService、删除用例、Content Type 删除服务和附件引用扫描等核心用例已按实际权限依赖读或组合契约；后台搜索、管理分页与 Relation API 仍留在具体实现，未被提前固化为 Public API。

Content 管理查询与 Relation 契约已拆分：`ContentAdminReadRepository` 在只读能力上增加后台分页和标题搜索，ContentListQuery 与 ContentSearchQuery 不再依赖具体实现；Relation 分为只读 `ContentRelationReadRepository` 和写入 `ContentRelationWriteRepository`，Related Posts Component 通过接口交集仅获得 Content／Relation 读取能力。Latest Content Component 同步迁移到基础只读契约。

Taxonomy 契约已拆分：`TaxonomyReadRepository` 覆盖 Taxonomy、Term、翻译、分页、搜索、选择与校验，`TaxonomyWriteRepository` 覆盖 CRUD、排序、关系同步和事务，组合契约供 ContentService 与后台写用例使用。Content 表单／搜索及所有 Taxonomy Query／翻译解析器仅依赖读取接口，Submit 与 Reorder 用例依赖组合接口；具体 Connection 未暴露到契约。

User 与 Role 契约已拆分：用户和角色分别提供 Read、Write 与组合接口，后台 User／Role Query 仅依赖读取能力，UserWriter 使用用户组合接口与角色读取接口，RoleWriter 仅依赖角色写入接口。Provider 保留具体类兼容绑定并将各接口指向同一数据库适配实例；密码哈希、管理员保护和事务边界未移出持久化实现。

Authentication 查询已隔离：`AuthenticationReadRepository` 定义凭据、管理员角色和 Permission 查询，`DatabaseAuthenticationRepository` 封装对应 SQL。Authenticator 不再持有 Connection，仅执行密码验证和 Session 生命周期；管理员对新增权限的兜底语义保持不变，旧 Connection 构造方式通过内部适配继续兼容。

Settings 与 Navigation 契约已拆分：两者分别提供 Read、Write 与组合接口。SystemSettingsPresenter 依赖设置读取，SaveSystemSettings 依赖设置组合契约；导航页面 Query、翻译解析器和前台 Component 依赖导航读取，NavigationWriter 仅依赖导航写入。Provider 为接口和兼容具体类绑定同一数据库适配实例，层级、排序和事务规则保持原实现。

Plugin State、Redirect 与 SEO 边界已拆分：Plugin 状态和 Redirect 分别提供 Read、Write 与组合契约，PluginManager 依赖状态组合契约及 Settings 组合契约，前台 Content Query 仅依赖 Redirect 读取。SeoRenderer 改为依赖 Settings 读取契约，不再要求具体数据库 Repository；Plugin 生命周期、301 语义和 SEO 转义规则保持不变。

Phase 9 收尾审计完成：Content、Taxonomy、User、Role、Settings、Navigation、Plugin State 与 Redirect 的稳定能力均已有按读写权限拆分的契约，Application Query、Presenter、中间件和业务用例已改为依赖最小接口。后台 Controller 不再持有数据库 Connection；Dashboard、Database Tools 和 Sitemap 构造参数中的 Connection 仅用于装配专门的 Query／维护服务，以兼容现有调用方式。具体 Repository 继续作为数据库 Persistence Adapter 保留在原命名空间，避免在同一阶段破坏既有 Public API，并由 Provider 将契约绑定到同一实例。SQLite 完整回归以及现有 MySQL／PostgreSQL 内容分页、布尔值、Taxonomy 与事务集成测试覆盖保持通过；接口未暴露 PDO、Connection 或 Query Builder。Phase 9 完成标准复核通过，下一步进入 Phase 10。

### 目标

隔离业务模型与数据库实现。

### 任务

1. 为稳定领域能力定义最小 Repository Contract。
2. 将现有数据库 Repository 移入 Infrastructure/Persistence。
3. 消除 Controller 对 Connection 的依赖。
4. 将 Driver-specific 行为放入 Adapter。
5. 检查分页、排序、时间、布尔值和事务在三种数据库中的一致性。
6. 禁止 Public API 暴露 PDO、Doctrine Connection 或具体 Query Builder。

### 迁移顺序

1. Content。
2. Translation 与 Relation。
3. Taxonomy。
4. User、Role 与 Permission。
5. Settings 与 Navigation。
6. Plugin State。
7. Redirect 与 SEO。

### 完成标准

Domain 和 Controller 不依赖具体数据库连接，关键集成测试覆盖三种数据库。

## 14. Phase 10：View、Presenter 与表单体系

进行中：新增后台通用 `partials/form-actions.jst`，统一独立表单底部的分隔线、主提交按钮、取消链接和无取消动作场景。User、Role、Language、Navigation Item、Taxonomy 与 Profile 表单已迁移；删除确认、列表筛选和主题编辑等具有不同交互语义的操作区暂不强行复用。

View Namespace 基础已建立：`ViewNamespaceRegistry` 将短命名空间映射到受控目录，`NamespacedViewLoader` 负责 Latte 内容读取、相对 extends／include 解析和稳定缓存标识，并拒绝目录逃逸与命名空间覆盖。Admin View 默认使用 `admin::`，旧的短 View 名调用由 Renderer 自动规范化；调用方也可注入 Registry 并渲染完整的 Plugin View 名称。公开用法与安全约束已记录于 `docs/api/view-namespaces.md`。

Plugin View Namespace 已接入应用装配：Admin Provider 注册共享 Registry，Plugin Manager 发现带 `views/` 目录的 Manifest 时，以 Manifest `name` 自动注册对应目录。Renderer 与 Plugin Manager 持有同一 Registry 实例，因此无需扫描或修改 Core 模板即可渲染 `plugin-name::template.jst`；禁用 Plugin 不会触发模板执行，命名空间仅描述已发现的文件边界。

Theme Component Override 已从 Renderer 提取到 `ComponentViewLocator`：解析顺序固定为 Theme、按稳定路径排序的 Plugin、Core，并在读取文件前验证 Component 名称，阻止目录逃逸。Renderer 仅负责从 Registry 获取 Component 数据并交给 Latte；覆盖规则、缺失 View 行为和 View 无写操作约束已记录于 `docs/components/view-resolution.md`。

模板架构护栏已加入：Architecture Test 扫描 Core、Plugin、Theme 与 Site View，禁止遗留 `.latte`，禁止模板访问 Container、数据库连接、查询 API 或文件写操作，并将 `|noescape` 限定到六个经过审查、由 `JSON_HEX_*` 编码数据支撑的 JSON Script Payload。默认 Theme 的 Component 返回值已经是 Latte `Html`，因此移除导航输出上多余的 `noescape`，继续依靠类型化安全 HTML 语义。

Presenter／View Model 约定开始落地：新增不可变 `AdminPageView`，显式承载 template、title 与展示 data，并验证 Core 短模板名和 Plugin 命名空间模板名。Plugin Presenter 与 Controller 已完成首个端到端迁移，Controller 不再解包约定式数组；其他 Presenter 将按模块渐进迁移，避免大爆炸式改写。约定记录于 `docs/api/admin-presenter-view-model.md`。

`AdminPageView` 已继续覆盖 Dashboard、Cache／Logs 与 Database Tools：三个 Presenter 均返回明确对象，Controller 统一调用对象的 render 方法，不再重复解包 view／title／data。Query、维护服务和 HTTP 状态处理保持原边界，现有 Presenter 集成测试已改为直接断言类型化属性。

User 与 Role Presenter 也已迁移到 `AdminPageView`，覆盖列表、新建／编辑表单、个人资料以及校验失败后的 422 错误回显。Controller 的状态码仍由 HTTP 协调层决定，View Model 只承载展示内容；密码 6 位规则、角色保护和写入异常边界未改变。

Taxonomy 的顶层列表、Term 分页列表及 Taxonomy／Term 共用表单已迁移到 `AdminPageView`。Presenter 继续负责翻译 fallback、分页 URL、Locale 选项和原文参考展示，Controller 只选择分支、协调提交并决定 200／404／422／重定向响应，不再按不同页面手工传递模板、标题和 data 三元组。

Core View 已从 PHP 源码目录迁入统一 `core/resources/views/`：后台页面位于 `admin/`，Core Component View 位于 `components/`。Admin Provider、Component Locator、架构扫描和全部渲染测试均使用新路径；`core/src` 不再混放 `.jst` 资源。Theme 与 Plugin 目录边界保持不变。

Installer View 已完成资源化：`InstallPresenter` 不再拼接 HTML，而是向 `core/resources/views/installer/install.jst` 提供错误、CSRF、命名路由和环境检查数据；Latte 负责默认转义，测试覆盖错误文本注入和管理员密码最少 6 位属性。至此 Core Admin、Component 与 Installer View 均位于统一 Resources，Layout／Page／Field／List／Partial／Component 分层、命名空间、Theme Override、Presenter View Model 和模板安全测试均已落地。Phase 10 完成标准复核通过，下一步进入 Phase 11。

### 目标

形成统一、可复用、可由 Plugin 扩展的服务端 View 层。

### 任务

1. 将 Core View 移至统一 Resources 目录。
2. 保持所有模板使用 `.jst`。
3. 明确 Layout、Page、Partial、Field View 和 Component View。
4. 建立 View Namespace。
5. 建立统一后台表单控件 Partial。
6. 建立 Presenter / View Model 约定。
7. 支持 Plugin View Namespace。
8. 支持 Theme Component Override。
9. 建立模板解析和转义测试。

### 完成标准

View 无数据库、Container 和写操作；Controller 不拼装复杂展示结构；Plugin 和 Theme 覆盖规则稳定。

## 15. Phase 11：Plugin Framework 对齐

已完成：新增 Public API `PluginLifecycle`、`PluginServiceProvider`、`PluginRegistrar` 与各类受控 Registrar。Provider 的 `register()` 只获得明确扩展能力，不暴露 Container、Connection 或具体 Repository。Plugin Manager 只加载 `PluginServiceProvider`，早期 `Plugin` 与 `PluginContext` 兼容层已删除。官方 HelloPlugin 与 ExternalOrderPlugin 均使用当前 API。

ExternalOrderPlugin 已迁移到 Provider API：Component 通过 `ComponentRegistrar` 注册，业务服务依赖 `ContentReadRepository`，配置通过 `SettingsReadRepository` 读取，Route 只使用受控 Registrar 的 CSRF 与 ResponseFactory。

CSRF Public API 已收紧为 `CsrfTokenProvider`，只定义 value 与 valid；具体 Session 实现 `CsrfToken` 同时绑定到契约。PluginRegistrar 与 ExternalOrderFormComponent 仅依赖该接口。

Content Type 扩展点已收紧为 `ContentTypeRegistrar`，现有 `ContentTypeRegistry` 实现该接口，PluginRegistrar 只暴露 register 能力而不允许 Plugin 枚举或读取 Registry 内部状态。Route Middleware 已由 Public `Route::middleware()` 和 `RouteRegistrar::group()` 提供受控注册，无需向 Plugin 暴露全局 Kernel。

Plugin Migration 已接入安装、重新安装与升级生命周期：`PluginMigrationRunner` 复用 Core 的跨数据库 `MigrationRunner`，但不向 Plugin 暴露 `Connection`。迁移版本按 `plugin:<name>:` 隔离，避免 Core 和不同 Plugin 使用相同文件名时冲突；重复执行、无迁移目录与非法 Plugin 标识均有集成测试。公开用法和数据库边界见 `docs/plugins/migrations.md`。

Plugin Service 扩展已通过只写的 `PluginServiceRegistrar` 接入应用 Container：扩展可按自有具体类及其实现的契约注册对象，但不能读取 Container、覆盖既有 Service 或使用 Core 具体类作为自有 Service。HelloPlugin 已改为 Service 与 Controller 注册，Router 通过现有 Controller Action 解析真实验证该链路。

Field Type 扩展已从 `EditableContentTypes` 内部的硬编码 match 迁移到 `FieldTypeRegistry`。内置类型和 Plugin 自定义类型现在共用 handle → factory 创建、实现类 → handle 导出链路；`FieldTypeRegistrar` 只暴露注册能力，并拒绝重复 handle、重复实现类和错误 factory 返回值。

Event 扩展已落地为轻量对象分发器，不引入当前仅由开发工具间接安装且要求 PHP 8.4 的第三方 EventDispatcher。Plugin 只通过 `EventListenerRegistrar` 注册 Listener，不能 dispatch Core 事件。`ContentCreated`、`ContentUpdated`、`ContentPublished`、`ContentUnpublished` 与 `ContentDeleted` 已接入真实 Application／Domain Service 链路，并覆盖事件负载、顺序、状态去重和删除成功后分发测试。

Plugin Translation 已接入现有 Admin `Translator` 与 Latte `t()` 调用链。Plugin Manager 按 Manifest name 自动注册 `lang/admin`，扩展文案必须使用 `plugin-name::key` 命名空间，解析顺序为当前后台 locale → fallback locale → 原 key。`TranslationRegistrar` 只暴露目录注册，不允许 Plugin 枚举或改写其他命名空间。

Plugin View 已收口为 `ViewRegistrar` 与 `ViewRenderer` 两个 Public 边界。Plugin Manager 按 Manifest name 自动注册 `views/`，Plugin 可渲染 `plugin-name::path.jst`，但无法读取 Namespace Registry、Latte Engine、Loader 或缓存路径。HelloPlugin 示例 View 已同时验证 View Namespace、Plugin Translation 和 Latte 默认转义链。

Phase 11 于 2026-09-27 完成。Routes、Middleware、Services、Events、Components、Field Types、Content Types、Views、Translations 与 Migrations 均已具备受控入口和运行链测试；`docs/api/plugin-public-api.md` 明确 Public／Internal 边界，架构测试禁止官方 Plugin 回退依赖 Container、Connection、具体 Repository 和内部 Registry。完整 ExternalOrder 验证链保持通过。

### 目标

让 Plugin 开发体验与 Core 的现代化结构保持一致。

### 任务

1. 定义 Plugin Service Provider。
2. 明确 Plugin `register` 与 `boot`。
3. 提供受控注册器：
   - Routes。
   - Middleware。
   - Services。
   - Events。
   - Components。
   - Field Types。
   - Content Types。
   - Views。
   - Translations。
   - Migrations。
4. 标注 Public API 与 Internal API。
5. 删除早期 PluginContext 兼容适配器。
6. 使用官方 Plugin 验证完整扩展链。

### 验证场景

```text
Product
→ ExternalOrderForm Component
→ Plugin Route
→ Request Validation
→ Application Service
→ Fake Third-party API Adapter
→ Response
```

### 完成标准

Plugin 可以在不修改 Core 的前提下实现完整功能，且不依赖 Core Internal Class。

## 16. Phase 12：目录与 Namespace 最终迁移

进行中：登录入口以及全部 Core 后台 Controller 已迁入 `Http/Controllers/Admin`；后台表单 Request 已迁入对应的 `Http/Requests` 目录；Installer、前台 Controller 与动态 Content 路由注册器也已归入 HTTP 层。主要后台、前台 Content、Content CRUD/类型删除、可编辑 Content Type 配置、Sitemap、Installer、Plugin 生命周期与 Core Update 用例已归入对应 Application Feature，前台 Content、Field、Sitemap、SEO 及 Installer Presenter 已归入 View。Application、HTTP、View 和 Provider 已不再直接执行文件系统探测、加载配置文件或构造 Monolog Handler；Sitemap Query 的 JOIN SQL、Installer 初始数据写入及运行环境探测也已下沉到 Infrastructure。后台与 Installer 的 View 数据准备已和 Latte 初始化、缓存目录操作分离，ThemeRenderer、ComponentRenderer 与 CommonMark ContentBodyRenderer 也已归入 Infrastructure View。兼容期 `AdminAccess` 已迁入 HTTP 层，实际路由授权继续由 Middleware 承担。Admin 与 Theme 旧 Namespace 文件已删除。Infrastructure Adapter 迁移已覆盖主要数据库 Repository、连接工厂、Core 与 Plugin 迁移执行器、PHP 配置加载、日志构造，Content、Theme、Backup、Installer、Update、Core Assets、Cache/Logs 与 HTTP 文件响应的文件系统实现，以及 View Loader、View Namespace 路径解析、Component View 定位、Template Catalog、活动 Theme 路径和 Plugin 文件发现；Core Updater 只保留维护模式、Migration、Health Check 和回滚流程编排，更新包校验已归入文件系统适配器。公共读写契约继续留在各 Feature 边界，只读生产调用方也已改依赖对应接口。其余 Driver Adapter 的物理迁移继续分批执行。生产调用方只使用当前类型；旧类名、双 Container binding 与别名等价测试已删除。迁移映射记录于 `docs/architecture/namespace-migration.md`。

Installer HTTP 调用链直接依赖 `InstallApplicationService`；旧 `Installer` 门面已删除。

认证与权限判断服务已迁入 `Application\\Auth\\Services`；HTTP、中间件和应用写用例均使用新类型，旧 `Auth\\Authenticator` 已删除。

Route PHP 文件及 Core／Plugin Translation Catalog 的路径验证与加载已下沉到 `Infrastructure\\Filesystem`，注册器与翻译服务只依赖最小加载契约；非法 Locale 无法借目录穿越读取 Catalog 目录外的 PHP 文件。

Dashboard、Database Tools 与 Sitemap Controller 已移除 `Connection`、具体 Repository／Backup 实现及内部 Query 构造兼容分支，只接收 Provider 完成装配后的 Query、Application Service 与 Presenter。

Cache／Logs、Plugin、Role 与 User Controller 也已移除 Repository、Manager、存储路径及 Presenter 构造依赖；对应 Query、Writer、Maintenance 和 Presenter 全部由 Provider 统一装配。

Settings 与 Installer Controller 已移除 Presenter／Application Service 的可选回退构造；生产与测试均从组合根传入完整用例依赖。

前台 `PublicContentController` 已移除 Content／Settings／Redirect Repository、Theme Renderer、Schema Registry 与 Content Type Registry 构造依赖，只协调已装配的 Homepage、Content、Not Found Query 和 Presenter。

Theme Controller 已移除主题路径、Settings Repository 和 Theme File Manager 构造兼容分支，只依赖已装配的 Theme Query 与 Writer。

Navigation Controller 已移除 Repository 与 Translation Resolver 构造依赖，列表、表单、写入和 Presenter 均由 Provider 装配；Controller 只保留请求协调和重定向 Locale 选择。

Taxonomy Controller 已移除 Repository、Content Type Registry 以及八个 Query／Service／Presenter 的可选构造分支；Provider 负责完整装配，Controller 保留 HTTP 协调与 Locale 上下文。

Content Controller 已移除具体 Content Repository、附件存储、Content Service、表单渲染器、Taxonomy／Template Adapter 以及九个用例对象的构造职责；生产装配集中在 Admin Provider，测试通过专用 Factory 显式构造同一依赖图。

Content 表单请求验证器改为显式注入；Public Media Controller 改依赖 `PublicMediaReader` 契约，不再接收附件存储并自行创建文件读取适配器。

Application Authenticator 已移除 `Connection` 联合类型和数据库适配器构造逻辑，只依赖 `AuthenticationReadRepository`；调用方必须显式注入该契约。

Content Type 删除事务改由 Content Repository 契约承载，不再直接依赖 `Connection`；附件读写通过 `ContentAttachmentStore` 契约，存储路径格式由独立值规则统一校验，Application、HTTP 与 View 不再引用具体附件文件实现。

Theme Template Catalog 新增 `TemplateCatalogReader` 只读契约；Content Application、Content Form Presenter 与 Content Type Controller 不再依赖 Infrastructure Catalog 实现。

Cache／Logs Application Query 与 Maintenance 已改依赖 `RuntimeStorageGateway`，移除字符串路径兼容构造和具体 `RuntimeStorage` 创建逻辑。

Theme Query 与 Writer 已改依赖 `ThemeFileGateway`，不再引用具体 `ThemeFileManager`；Theme 路径校验、目录遍历和写操作继续由 Infrastructure Adapter 实现。

Dashboard 与 Database Tools Application 用例已改依赖 `DatabaseOperations` 和 `BackupGateway`；表统计、Migration 数量、完整性检查、优化 SQL 与 Driver 分支下沉到 `DatabaseOperationsAdapter`。

Plugin Manager 已改依赖 `PluginDiscoveryGateway` 与 `PluginMigrationGateway`，不再创建或引用具体 Plugin 文件发现及数据库迁移实现；缺少 Discovery 时明确拒绝 boot。

Installer Application Service 已改依赖 `InstallationDatabaseGateway`、`DatabaseConfigGateway` 与 `InstallationLockGateway`；连接探测、Migration、Seeder 和文件写入实现均由 Provider 在组合根装配。

Installer 输入校验已迁入 `Application\\Installer\\InstallationInput`，应用服务不再反向依赖 HTTP Request；迁移前类名已删除。

Content 上传结构整理已迁入 `Application\\Content\\ContentUploadInput`，附件协调器不再创建 HTTP Request 类；迁移前类名已删除。

Content 创建、更新与附件协调用例改为只接收 Controller 提取的上传文件数组，不再接收完整 `ServerRequestInterface`；HTTP 请求解析继续留在 Controller。

Content 创建与更新用例以 `ContentOperationDenied` 表达完整 HTML 页面权限拒绝，Controller 负责映射 HTTP 403；Application 不再抛出 HTTP Exception。

Template Catalog 只读契约迁入 `Application\\Content`，Content Service 不再反向依赖 View 命名空间；原 View 接口名与 Container ID 保持兼容。

Plugin Manager 改依赖 `PluginViewRegistrar` 最小注册契约，不再依赖具体 `ViewNamespaceRegistry`；公开 `ViewRegistrar` 继承该契约以保持 Plugin API 兼容。

System Settings Request 改依赖 `ThemeSelectionGateway`，主题选项与首页模板探测由 Theme 文件适配器实现；Request 不再自行创建 Infrastructure 对象。

`ResponseFactory` 已移除 `LocalResponseFileReader` 默认构造，只依赖可选的 `ResponseFileReader` 契约；生产组合根注入本地文件适配器，纯文本、HTML、JSON 与 Redirect 调用保持轻量兼容。

Installer 与 Maintenance 中间件已改依赖 `InstallationLockGateway`、`MaintenanceModeGateway`，Route Provider 从组合根解析契约，不再向 HTTP 层传递具体锁文件适配器类型。

`AdminLocale`、`TranslationRegistry` 与 `Translator` 已移除 PHP Catalog Loader 的内部默认构造，统一依赖 `TranslationCatalogLoader`；生产由 Foundation Provider 装配，本地 View 兼容构造在 Infrastructure 内完成。

`RouteRegistrar` 已移除 `LocalRouteFileLoader` 默认构造；内联 get/post/group 注册保持可用，路由文件加载必须由组合根注入 `RouteFileLoader`。

`ViewNamespaceRegistry` 已移除 `LocalViewPathResolver` 默认构造，只依赖显式注入的 `ViewPathResolver`；Provider 与 Infrastructure View 负责本地路径适配器装配。

Installer Controller 与 Presenter 已改依赖 `EnvironmentGateway`，PHP 版本及扩展探测实现继续位于 Infrastructure，Provider 同时保留旧 EnvironmentChecker 服务 ID。

Installer Presenter 已改依赖 `InstallerView` 渲染契约，不再接收模板路径、缓存路径或自行构造 Latte Renderer；Provider 负责唯一生产装配。

后台 Controller、Presenter 与 `AdminPageView` 已改依赖 `AdminView` 契约，不再类型依赖 Infrastructure Latte Renderer；Admin Provider 同时绑定契约、具体实现和旧服务 ID。

前台 `PublicContentPresenter` 与 `PublicFieldPresenter` 已分别改依赖 `ThemeView`、`ContentBodyView`；Theme/正文具体渲染实现保留在 Infrastructure，View 层不再创建 CommonMark Renderer。

Taxonomy Repository 不再临时创建 Settings Repository；生产装配显式注入 `SettingsReadRepository`，旧构造方式仅以同一 Connection 的只读查询兼容持久化 Content Type 绑定检查。

Installer Controller 已移除 `ResponseFactory` 可选回退构造，响应创建能力必须由 Provider 显式注入。

三个前台 Controller 已移除 `ResponseFactory` 可选回退构造，Theme Provider 与测试装配均显式传入响应创建能力。

统一 `ExceptionHandler` 已移除 `ResponseFactory` 可选回退构造，Route Provider 显式注入共享响应工厂。

Cache/Logs、Database Tools、Language、Login、Media、Plugin、Role 与 User 后台 Controller 已移除 `ResponseFactory` 回退，仅接受 Admin Provider 的显式装配。

其余 Content、Content Type、Dashboard、Navigation、Settings、Taxonomy 与 Theme 后台 Controller 也已移除 `ResponseFactory` 可选回退；全部后台 Controller 统一由组合根注入响应工厂，架构测试扫描完整 Controller 目录防止回退逻辑重新出现。

`ContentTypeAdminController` 的 Taxonomy、Template Catalog、Router 与删除用例也改为必需依赖，不再因测试或旧式构造缺参而静默跳过分类法存在性、模板类型、路由冲突或删除资格检查；Provider 与测试均装配完整验证链。

Content 表单展示与联想搜索链的 Taxonomy／Template Catalog 依赖已改为必需项；`ContentFormPresenter` 不再静默隐藏模板和分类字段，`ContentSearchQuery` 不再在缺少 Taxonomy Adapter 时返回伪空结果，测试工厂与生产 Provider 使用一致的完整依赖图。

`EditableContentTypes` 已停止自行创建 `FieldTypeRegistry` 和注册内置类型，改为只接受组合根配置完成的 Registry；生产与测试均显式执行默认 Field Type 注册，因此 Plugin 自定义 Field Type 和内置 Field Type 始终共享同一持久化映射链。

后台 `DeleteContent` 用例的 `EventDispatcher` 已改为必需依赖，成功删除后始终进入统一 `ContentDeleted` 分发链；无 Listener 的测试使用空 Dispatcher，而不再通过省略依赖关闭事件能力。`ContentService` 的历史可选构造暂按 Public API 兼容策略保留。

PDO `Connection` 实现已迁入 `Infrastructure\Database`，Persistence／Database／Backup Adapter、Migration 与生产 Provider 全部使用新类型。

Phase 12 于 2026-10-02 完成。Controller、Request、Application Service、Presenter 与主要 Adapter 已迁入目标目录；2026-10-03 删除全部旧 Namespace 入口，Migration DSL 继续作为 Plugin 可用的数据库 Public API。Infrastructure 边界测试禁止 PDO 实例化、文件读写、目录扫描以及 Latte／Monolog 具体初始化重新出现在 Infrastructure 之外。

Core Updater 已改依赖 `CoreUpdateStorage`、`CoreMigrationGateway` 与 `MaintenanceModeGateway`；资源 Manifest、目录切换、数据库连接和 Migration Runner 均留在 Infrastructure Adapter，旧 `Update\\CoreUpdater` 构造入口已删除。

### 目标

在职责已经稳定后完成物理目录整理。

### 任务

1. 按 `ARCHITECTURE.md` 迁移 Controller、Request、Middleware 和 Provider。
2. 迁移 Application Services。
3. 迁移 Domain 类型。
4. 迁移 Infrastructure Adapter。
5. 更新 Composer PSR-4 映射。
6. 使用兼容类或类型别名处理需要过渡的 Public API。
7. 更新所有测试、官方 Plugin 和 Theme 引用。
8. 添加禁止旧 Namespace 新增依赖的 Architecture Test。

### 注意

目录迁移放在后期，是为了避免职责拆分与文件移动同时发生，降低审查和回归难度。

### 完成标准

目标 Namespace 与物理目录一致，旧 Internal Namespace 无残留调用。

## 17. Phase 13：Bootstrap 清理与性能检查

已移除无生产消费者的 `AdminAccess` 类、测试和文档；认证、权限和登录重定向均由现有 Middleware 链承担。

Foundation／Admin Provider 已移除 8 个仅在装配当场使用、无后续解析者的 Container 项：配置 Loader、日志 Factory、Route File Loader、Response File Reader 与 View Path Resolver 的具体／契约绑定。适配器对象仍由持有它们的 Config、Registrar、ResponseFactory 和 View Namespace Registry 直接引用，不改变运行行为。

Admin／Plugin／Theme Provider 继续移除 7 个内部装配绑定：Database Operations、Runtime Storage、Plugin Discovery 的具体／契约 ID，以及 Active Theme Locator 具体 ID。对应对象仍直接注入 Query、Maintenance、Plugin Manager 或用于创建 Theme Catalog，不作为可解析的公共服务暴露。

Theme Template Catalog 已增加请求生命周期内的 Manifest、模板目录、路径、分类选项与文件映射缓存。同一请求重复渲染内容类型表单或校验模板时不再反复读取 `theme.json`、执行 `glob` 和 `realpath`；Theme 文件管理操作完成后通过重定向进入新请求，自然获得新 Catalog 快照。

新增 `scripts/performance-baseline.php`，在隔离 SQLite 站点中分别采样完整 Bootstrap、首页、Content 详情和后台 Content 列表，输出平均值、P95、最大耗时及峰值内存 JSON。基线不使用硬编码 CI 时间阈值，避免把机器负载抖动误判为回归；复测方法记录于 `docs/architecture/performance-baseline.md`。

Router 已缓存当前实例编译完成的 FastRoute Dispatcher，同一应用实例处理多个请求时不再重复编译完整路由表；任何新增 Route 都会使缓存立即失效，兼容测试、长驻 Worker 和 Plugin 在首次 dispatch 前完成路由注册的现有流程。配置 PHP 文件可能读取环境变量与运行期数据库配置，因此暂不采用可能产生陈旧值的跨请求静态缓存。

运行期存储边界复核完成：数据库、生成配置、安装／维护锁、日志、Latte 缓存、附件、备份和更新工作区全部位于 `storage/`；架构测试禁止生产源码使用系统临时目录，并锁定关键路径。完整清单见 `docs/architecture/runtime-storage.md`。

存储边界测试发现并移除了 `InstallerViewRenderer` 的系统临时目录兼容回退；Installer View 现在必须显式接收资源目录与 `storage/` 缓存目录，生产 Provider 和测试均使用完整装配。

Phase 13 于 2026-10-02 完成。Bootstrap 已移除无消费者服务与过渡绑定，模板及路由元数据具备安全的请求生命周期缓存，常用页面已有可复测性能基线，运行期文件边界保持在 `storage/`。下一步进入 Phase 14 的跨数据库、升级与安全验收。

### 目标

删除过渡层并验证新架构没有引入不必要开销。

### 任务

1. 删除已无调用的旧 Bootstrap 注册代码。
2. 删除过期兼容适配器；Public API 适配器按版本策略处理。
3. 缓存配置、路由和模板元数据，但不得强制 Redis。
4. 检查每次请求的 Service 构造和文件扫描。
5. 建立首页、后台列表和内容详情页的性能基线。
6. 检查日志、缓存和临时文件仍位于 `storage/`。

### 完成标准

Bootstrap 只编排 Application 和 Provider；常用请求性能不低于重构前的合理基线。

## 18. Phase 14：跨数据库、升级与安全验收

### 目标

确认重构满足发布要求，而不只是本地 SQLite 可运行。

### 数据库矩阵

- SQLite。
- MySQL。
- MariaDB。
- PostgreSQL。

### 升级矩阵

1. 重构前稳定版本 → 新版本。
2. 已安装 Plugin 的站点升级。
3. 自定义 Theme 的站点升级。
4. 含 Content Translation 和动态 Field 数据的站点升级。
5. 失败后的回滚与维护模式恢复。

### 安全检查

- Authentication bypass。
- Permission bypass。
- CSRF。
- XSS 与模板转义。
- 上传 MIME、扩展名、大小和路径穿越。
- Open Redirect。
- Plugin Route 权限。
- 日志敏感信息。
- Installer 重入。

### 完成标准

跨数据库、升级、回滚和安全测试通过，Core 可安全替换，Site、Plugin、Theme 和 Storage 不被覆盖。

### 当前验收状态

Phase 14 的本地验收于 2026-10-03 完成：SQLite 完整套件为 396 tests、10539 assertions、0 warnings，PHP CS Fixer 与 `git diff --check` 通过。登录和退出均轮换 Session ID，Session Cookie 使用 HttpOnly、SameSite=Lax 并在 HTTPS 下启用 Secure；全局响应统一设置防 MIME sniffing、点击劫持、Referrer 和敏感浏览器能力安全头；未知异常日志不记录消息、Trace、Body 或 Header。Redirect 只允许安全的站内目标，并拒绝旧数据中的开放重定向；Installer 重入、安装锁与数据库配置落盘失败已有回归保护；Core Migration 失败会恢复旧 Core、撤销新版本资源、退出维护模式，并保留 Site、Plugin、Theme 和 Storage。

MySQL、MariaDB 与 PostgreSQL 的真实服务矩阵已加入 `.github/workflows/tests.yml`，本地运行方式见 `docs/database/testing.md`。当前机器没有可用的无密码测试数据库，因此 4 个外部 Driver 用例在本地明确 skipped；Phase 14 只有在该工作流首次全部通过后才能标记为最终完成。

发布包 `dist/jinstan-1.0.0.zip` 已完成实际构建与独立验证。Composer 生产依赖在模拟最终目录结构的临时工作区生成，不修改当前开发 Vendor；验证器确认压缩包不含测试工具或运行数据、资源 Manifest 哈希一致，并从干净解压目录完成 `/install`、管理员登录、后台访问及 CLI 数据库状态检查。构建与验证步骤见 `docs/upgrade/release-package.md`。

## 19. 推荐提交粒度

推荐每个阶段拆成以下类型的提交：

```text
test: 锁定现有行为
refactor: 添加新抽象但不迁移行为
refactor: 迁移一个调用方
refactor: 迁移剩余调用方
docs: 更新公共 API
chore: 删除确认废弃的旧实现
```

禁止将以下内容放进同一个大提交：

- 大规模文件移动。
- 行为修改。
- 数据库 Migration。
- 格式化全部代码。
- Public API 删除。

## 20. 单个模块迁移检查表

迁移任一模块时逐项确认：

- [ ] 已有行为测试。
- [ ] Route 有稳定名称。
- [ ] 横切安全进入 Middleware。
- [ ] 资源权限进入 Policy 或明确授权服务。
- [ ] 输入由独立 Validator 处理。
- [ ] Controller 不直接查询数据库。
- [ ] 写操作由 Application Service 管理。
- [ ] 事务边界明确。
- [ ] Repository 返回领域对象或明确查询结果。
- [ ] Presenter 负责展示数据转换。
- [ ] View 不访问 Container 或数据库。
- [ ] 异常由统一 Handler 转换。
- [ ] 三种数据库影响已检查。
- [ ] 多语言与 SEO 影响已检查。
- [ ] Plugin 和 Theme 兼容性已检查。
- [ ] Public API 文档已更新。
- [ ] 测试和格式检查通过。

## 21. 整体完成定义

只有同时满足以下条件，现代化重构才算完成：

1. 所有 HTTP 请求经由统一 Kernel 和 Middleware Pipeline。
2. 路由不再集中硬编码于 Bootstrap。
3. Controller 只处理 HTTP 协调。
4. 业务用例位于 Application Layer。
5. Domain 不依赖 HTTP、模板和数据库实现。
6. Repository Contract 与 Persistence Adapter 边界清晰。
7. View 只负责展示，统一使用 `.jst`。
8. Plugin 使用稳定 Public API 完成扩展。
9. Article、Page、Product 继续共享统一 Content API。
10. SQLite、MySQL/MariaDB、PostgreSQL 关键测试通过。
11. `/install` 仍是普通用户唯一必要安装入口。
12. Core 更新不会覆盖 Site、Plugin、Theme、Storage 或用户配置。
13. 安全、升级、回滚和文档验收完成。
14. 没有为了模仿大型框架引入超出项目需要的复杂机制。
