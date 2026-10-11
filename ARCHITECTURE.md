# Jinstan 现代化架构规范

## 1. 文档地位

本文档定义 Jinstan 的目标架构、开发约定与公共扩展边界。

规范关键词采用以下含义：

- **必须**：不可违反的系统约束。
- **应该**：默认做法，偏离时必须说明理由并补充测试。
- **可以**：按具体场景选择。

开发行为首先遵守根目录 `AGENTS.md`，系统架构和代码组织遵守本文档。重构步骤与验收方式见 `REFACTORING_PLAN.md`。

## 2. 架构目标

Jinstan 不依赖 Laravel、Symfony Full Stack、CodeIgniter、ThinkPHP 等完整 Web Framework，但必须提供接近现代 PHP Web Framework 的开发体验。

熟悉主流框架的开发者应能凭借以下通用链路快速理解系统：

```text
Request
→ Kernel
→ Middleware
→ Router
→ Controller
→ Application Service
→ Domain / Repository
→ View or Response
```

目标是：

- 目录和命名符合现代 PHP 开发者直觉。
- Controller、Middleware、Request、Service、Repository、View 职责明确。
- Core 保持轻量、可替换、可升级。
- Site、Plugin、Theme 无需修改 Core。
- SQLite、MySQL/MariaDB、PostgreSQL 始终处于同等架构地位。
- 兼顾自由 Content Type、多语言、SEO 和插件扩展。
- 不为模仿框架而复制不需要的复杂能力。

## 3. 总体架构

Jinstan 采用：

> 模块化 MVC + Application Service + Domain Model + Repository + Infrastructure Adapter

MVC 主要约束 HTTP 与界面层，不要求数据库、Migration、缓存、插件加载器等基础设施伪装成 Model。

```text
┌────────────────────────────────────────────────────┐
│ Presentation                                       │
│ Route / Middleware / Controller / Request / View   │
└─────────────────────────┬──────────────────────────┘
                          │ 调用用例
┌─────────────────────────▼──────────────────────────┐
│ Application                                        │
│ Command / Query / Application Service              │
└─────────────────────────┬──────────────────────────┘
                          │ 使用领域能力
┌─────────────────────────▼──────────────────────────┐
│ Domain                                             │
│ Entity / Value Object / Policy / Repository API    │
└─────────────────────────┬──────────────────────────┘
                          │ 由适配器实现
┌─────────────────────────▼──────────────────────────┐
│ Infrastructure                                     │
│ Database / Filesystem / Cache / Logger / Template  │
└────────────────────────────────────────────────────┘
```

依赖只能由外向内。Domain 不得依赖 HTTP、模板、数据库驱动或具体插件。

## 4. 目标目录

项目顶层目录保持稳定：

```text
core/
site/
plugins/
themes/
config/
public/
storage/
tests/
vendor/
```

Core 目标目录：

```text
core/src/
├── Application/
│   └── <Feature>/
│       ├── Commands/
│       ├── Queries/
│       └── Services/
├── Domain/
│   └── <Feature>/
│       ├── Entities/
│       ├── ValueObjects/
│       ├── Contracts/
│       └── Services/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   ├── Frontend/
│   │   └── Installer/
│   ├── Middleware/
│   ├── Requests/
│   ├── Responses/
│   ├── Routing/
│   └── Kernel.php
├── Infrastructure/
│   ├── Database/
│   ├── Persistence/
│   ├── Filesystem/
│   ├── Cache/
│   ├── Logging/
│   └── View/
├── View/
│   ├── Presenters/
│   ├── Components/
│   └── Forms/
├── Providers/
├── Events/
├── Exceptions/
└── Support/

core/resources/views/
├── admin/
├── frontend/
└── installer/

core/routes/
├── admin.php
├── web.php
└── install.php
```

目录服务于职责，不要求为每个功能建立空目录。新抽象只有在存在真实使用场景时才创建。

## 5. 请求生命周期

### 5.1 统一入口

`public/index.php` 只能：

1. 加载自动加载器。
2. 创建 Application。
3. 将 PSR-7 Request 交给 HTTP Kernel。
4. 发出 PSR-7 Response。

入口不得注册业务路由、直接查询数据库或拼接 HTML。

### 5.2 Application 与 Kernel

Application 负责容器、Provider 生命周期和全局配置。HTTP Kernel 负责：

- 全局 Middleware。
- 路由分发。
- 异常转 Response。
- 请求结束后的清理。

Kernel 不包含内容、用户、主题等业务规则。

### 5.3 Middleware

以下横切能力必须优先使用 Middleware：

- 安装状态检查。
- Session 启动。
- Authentication。
- Permission。
- CSRF。
- Locale 解析。
- Maintenance Mode。
- 安全响应头。

Controller 不应重复实现相同的认证、权限与 CSRF 模板代码。资源级权限仍可在 Controller 或 Policy 中判断。

### 5.4 Routing

Core 路由必须集中在 `core/routes/`，按 Web、Admin、Installer 分组。Router 应支持：

- HTTP Method。
- 路径参数。
- Route Name。
- Prefix Group。
- Middleware Group。
- Controller Action。

Plugin 路由由 Plugin 在明确生命周期中注册。Theme 不得注册业务路由。

路由名称应该稳定，View 与重定向应该通过路由名称生成 URL，避免散落字符串路径。

## 6. MVC 规范

### 6.1 Controller

Controller 只负责：

- 接收路由参数和 Request。
- 调用已完成认证、权限和输入校验的结果。
- 调用 Application Service、Command 或 Query。
- 选择 View、Redirect、JSON 或文件 Response。
- 将已知业务异常映射为用户可理解的结果。

Controller 禁止：

- 直接编写 SQL。
- 实现跨多个对象的业务事务。
- 拼接大段 HTML。
- 构造复杂列表展示数组。
- 处理底层文件路径安全逻辑。
- 读取 Service Container 获取依赖。
- 捕获所有 `Throwable` 后隐藏编程错误。

Controller 应使用构造器注入。建议每个公开 Action 保持短小；当 Action 包含可独立命名的业务流程时，必须提取用例服务。

### 6.2 Request 与 Validation

写操作必须使用独立 Request Validator 或等价输入对象处理：

- 输入提取与 normalization。
- 字段校验。
- 文件上传基础校验。
- 错误消息。
- 安全的已验证数据输出。

Request Validator 不执行数据库写入。动态 Content Field 的业务校验继续由 Field Type 和 Content Service 执行，不在 Controller 中按 Field 分支。

### 6.3 Model

Jinstan 中的 Model 指业务模型，而不是默认指一张数据库表。

Content、Content Type、Field、Translation、Relation 和 Taxonomy 都属于业务模型。Article、Page、Product 必须共享统一 Content API，不得为了模仿 Active Record 分别建立核心 CRUD 基础设施。

Plugin 自有且简单的数据表可以使用轻量 Record Model 或 Query Builder，但不得将具体数据库实现暴露为 CMS 公共 API。

### 6.4 View

所有 Latte 模板必须使用 `.jst` 扩展名。View 只负责展示：

- 接收已准备的标量、数组或只读 View Model。
- 输出转义后的 HTML。
- 使用 Layout、Partial 和 Component。

View 禁止：

- 查询数据库。
- 访问 Container。
- 修改文件或数据。
- 执行业务授权。
- 调用具有副作用的 Service。

### 6.5 Presenter / View Model

以下逻辑应放入 Presenter 或 View Model：

- 表格列定义。
- 状态标签和显示名称。
- 分页链接。
- 编辑、删除、翻译 URL。
- Locale fallback 的展示说明。
- 表单页签和帮助文本。

Presenter 不写数据库、不处理事务、不决定业务权限。

## 7. Application Layer

Application Layer 表达一次用户用例，例如：

- CreateContent。
- UpdateContent。
- PublishContent。
- DeleteContent。
- InstallPlugin。
- ChangeTheme。
- RunDatabaseIntegrityCheck。

写用例可采用 Command/Handler 或命名明确的 Application Service。读用例可采用 Query Service。项目不强制为每个操作制造 DTO、Command 和 Handler；只有当它能明确边界、复用流程或改善测试时才创建。

事务边界应位于 Application Service，不位于 Controller、View 或 Repository。

## 8. Domain Layer

Domain 包含稳定的 CMS 概念与规则：

- Content 与 Content Type。
- Field Definition 与 Field Type。
- Content Translation。
- Relation。
- Taxonomy。
- User、Role 与 Permission。
- Plugin 生命周期契约。
- Theme、Layout、Template 和 Component 契约。

Domain 对象必须保持框架无关，不接收 PSR Request，不返回 HTTP Response，不依赖 Latte，不依赖 PDO 或 Doctrine 的具体连接对象。

业务事件必须使用对象，例如 `ContentCreating`、`ContentCreated`、`ContentPublishing`、`ContentPublished`。事件名称必须准确表达发生时机。

## 9. Repository 与数据库

Repository 是持久化边界：

- Domain 或 Application 依赖 Repository Contract。
- Infrastructure 提供数据库实现。
- Repository 返回领域对象或明确的查询结果。
- Controller 和 View 不得直接依赖数据库 Connection。

所有 Schema 修改必须通过 Migration。Core SQL 必须兼容 SQLite、MySQL/MariaDB 和 PostgreSQL；数据库差异必须隔离在 Driver Adapter。

禁止把 Doctrine Connection、PDO 或特定 Query Builder 类型暴露为稳定公共扩展 API。

## 10. Container 与 Service Provider

Container 必须支持明确绑定和构造器注入。禁止 Facade 式静态服务访问和运行时 Service Locator。

Provider 用于分解当前集中式 Bootstrap：

```text
register()
→ 只注册服务与配置，不执行业务操作

boot()
→ 在所有服务注册后，注册路由、事件、组件等
```

建议的 Core Provider：

- FoundationServiceProvider。
- DatabaseServiceProvider。
- ContentServiceProvider。
- AdminServiceProvider。
- ThemeServiceProvider。
- PluginServiceProvider。
- RouteServiceProvider。

Provider 不得在普通请求中偷偷修改 Schema。

## 11. Plugin 开发模型

Plugin 必须拥有清晰且与 Core 一致的结构：

```text
plugins/<PluginName>/
├── plugin.php
├── src/
│   ├── Controllers/
│   ├── Services/
│   ├── Repositories/
│   ├── Events/
│   └── PluginServiceProvider.php
├── routes/
│   ├── web.php
│   └── admin.php
├── resources/
│   ├── views/
│   └── lang/
├── migrations/
├── tests/
└── README.md
```

Plugin 可以注册 Service、Route、Middleware、Event Listener、Component、Field Type、Content Type、Admin Page、Permission、Migration、Settings、Translation 和 CLI Command。

Plugin 只能依赖标注为 Public API 的 Core Contract。Plugin 不得修改 Core、vendor 或 Theme 原文件。

## 12. Theme 与 Component

Theme 决定整体视觉，仅包含 Layout、Template、Partial、Component View Override、Assets、Theme Settings 和 Theme Translation。

Component 是页面中的可复用展示或轻交互单元。Component 可以调用只读 Query Service，但大型业务流程必须委托给 Application Service。

模板解析顺序保持：

```text
Content Template
→ Content Type Default Template
→ Theme Fallback
```

## 13. 安全规范

任何后台或写操作必须具备：

- Authentication。
- Permission。
- CSRF。
- Validation。
- Output escaping。

危险操作必须有明确二次确认，且服务端仍要完整校验 Authentication、Permission、CSRF 和目标对象。上传必须校验 MIME、扩展名、大小和目标路径。

日志不得记录密码、API Secret、Session Token、CSRF Token 或其他凭证。

## 14. 错误与异常

必须由统一 Exception Handler 将异常转换为 Response：

- Validation Exception → 422 或表单错误。
- Authentication Exception → 登录跳转或 401。
- Authorization Exception → 403。
- Model Not Found → 404。
- CSRF Exception → 419。
- Domain Conflict → 409 或可读表单错误。
- 未知异常 → 500，并记录安全日志。

生产环境不得把堆栈、数据库信息或文件系统路径暴露给用户。

## 15. 测试结构

测试分为：

```text
tests/
├── Unit/
├── Integration/
├── Feature/
├── Architecture/
└── Support/
```

- Unit：领域对象、Validator、Presenter、纯 Service。
- Integration：Repository、Migration、文件存储和第三方适配器。
- Feature：通过 HTTP Kernel 验证完整请求链路。
- Architecture：依赖方向、Public API 和目录边界。

关键数据库行为必须覆盖 SQLite、MySQL 和 PostgreSQL。Bug 修复应先补充能够复现问题的测试。

## 16. Public API 与兼容性

以下类型可以成为 Public API，但必须明确标注和文档化：

- Plugin Contract 与 Plugin Context。
- Component、Field Type、Content Type 注册接口。
- Route 与 Middleware 注册接口。
- Event 对象。
- Repository Contract 中允许扩展使用的部分。
- View Namespace 与 Theme Override 接口。

未标注 Public API 的类均视为 Internal。重构期间应优先添加新接口和兼容适配器，再迁移调用方，最后删除旧接口。

## 17. 明确禁止

- 引入完整 Web Framework 承载整个项目。
- 将整个系统机械压入 Controllers、Models、Views 三个目录。
- 使用 Controller 直接 SQL 或直接维护事务。
- 为 Article、Page、Product 分别创建三套核心 CRUD。
- 使用静态 Facade、全局可变状态或隐式 Service Locator。
- 让 Theme 实现订单、支付或第三方 API 等业务。
- 让 Plugin 修改 Core、vendor 或 Theme 原文件。
- 在普通请求或 Plugin boot 中隐式建表。
- 为尚不存在的需求提前建立复杂抽象。
- 新增或引用 `.latte` 模板。

## 18. 架构决策标准

每次新增公共能力必须回答：

1. 这是 HTTP 展示、应用用例、领域规则还是基础设施？
2. 是否可以使用现有依赖完成？
3. 是否泄漏数据库或第三方实现？
4. 是否影响三种数据库？
5. 是否影响多语言、SEO、安全和 Core 升级？
6. Plugin 是否可以不修改 Core 完成同类扩展？
7. 是否有测试与公共 API 文档？

无法明确归属的代码不得直接加入 Controller 或 Bootstrap，应先澄清职责。

