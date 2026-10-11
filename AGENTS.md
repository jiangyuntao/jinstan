# AGENTS.md

## 1. 作用

本文件用于约束 Codex / AI Agent 在本项目中的开发行为。

目标不是一次性生成“大而全 CMS”，而是按阶段实现一个小核心、可升级、可扩展、跨数据库、面向 SEO niche 网站的 CMS。

任何实现都必须优先服从本文件，其次服从 `ARCHITECTURE.md`，再其次才是具体任务描述。

---

## 2. 项目目标

构建一套：

- PHP 8.3+
- Composer
- SQLite 默认
- MySQL / MariaDB / PostgreSQL 可选
- Tailwind CSS + Alpine.js 后台
- Latte Theme
- 自由 Content Type
- 可扩展 Field
- Theme / Layout / Template / Component
- Plugin / Event
- 多语言
- SEO First
- Core 可安全升级

的轻量 CMS。

最终普通用户安装方式：

```text
访问 /install
```

CLI 不作为普通用户安装前置条件。

---

## 3. 开发总原则

### 3.1 不使用完整 Web Framework

禁止直接基于：

- Laravel Application
- Symfony Full Stack
- Yii
- CakePHP
- CodeIgniter

构建整个项目。

可以使用独立 Composer Package，例如：

- PSR HTTP components
- FastRoute
- Doctrine DBAL
- Doctrine Migrations
- Latte
- Symfony EventDispatcher
- Symfony Console
- Symfony Cache
- Monolog

### 3.2 不重新造基础轮子

如果已有成熟、轻量、独立、许可合适的 Composer Package，优先集成。

不要自行开发：

- Router 底层算法
- PSR Request / Response
- Logger
- Cache Engine
- Template Parser
- Event Dispatcher
- Database Driver

项目自己的工作是定义 CMS 公共 API 和边界。

### 3.3 简单优先

禁止为了“以后可能用到”而提前构建复杂抽象。

优先：

- 简单类
- 明确接口
- 普通 PHP
- 清晰依赖注入
- 小文件
- 可读 SQL / QueryBuilder

避免：

- 过多设计模式
- 多层 Repository 套 Repository
- DTO 泛滥
- Service Locator
- Facade 泛滥
- 魔法方法泛滥
- 隐式全局状态
- 为了抽象而抽象

---

## 4. 核心术语必须严格一致

### 4.1 Content Type

表示“数据是什么”。

例如：

- Article
- Page
- Product
- Doctor
- Clinic

不要把 Content Type 称作 Plugin 或 Component。

### 4.2 Field

表示 Content Type 的字段及字段行为。

### 4.3 Theme

表示网站整体视觉包。

### 4.4 Layout

表示页面整体骨架。

### 4.5 Template

表示某一条 Content 如何显示。

### 4.6 Component

表示页面中可复用的展示/轻交互单元，例如：

- Breadcrumb
- RelatedPosts
- LatestArticles
- CTA
- SearchForm
- ExternalOrderForm

项目中不要再使用 `Module` 表示展示单元。

如代码或文档中出现 Module，必须明确其含义；除非后续架构文档重新定义，否则不要引入 Module 作为新的核心概念。

### 4.7 Plugin

表示系统能力和行为扩展。

Plugin 可以注册 Component、Field Type、Content Type、Route、Event Listener 等，但 Plugin 本身不等于这些对象。

---

## 5. 目录边界

目标目录：

```text
core/
site/
plugins/
themes/
config/
public/
storage/
docs/
tests/
vendor/
```

### 5.1 Core

`core/` 必须可以独立升级替换。

禁止站点定制直接修改 Core。

### 5.2 Site

`site/` 仅用于当前站点少量特殊逻辑。

如果某能力可能复用到其他网站，应优先做 Plugin。

### 5.3 Plugins

Plugin 不得修改 Core、vendor、Theme 原文件。

### 5.4 Themes

Theme 只处理展示，不实现订单、支付、第三方 API 等复杂业务。

### 5.5 Storage

运行期数据、缓存、日志、数据库、备份不得放入 Core。

---

## 6. 第三方依赖规则

新增 Composer Package 前必须检查：

1. 是否真的需要。
2. 是否可以用现有依赖完成。
3. Package 是否仍然维护。
4. License 是否适合商业化。
5. 是否引入大量无关依赖。
6. 是否可以通过 CMS 自己的接口进行隔离。

不要让业务代码直接依赖第三方实现细节。

例如不要在 Theme / Plugin 公共 API 中暴露 Doctrine Connection，除非这是明确的低层 Extension API。

---

## 7. PHP 编码要求

- PHP 8.3+。
- 开启 strict types。
- 遵循 PSR-12 或项目统一代码风格。
- 代码以简洁、自然、贴近业务为优先，避免模板化或明显的 AI 生成式命名与结构。
- 所有自有 PHP 源码必须以正常的多行结构书写：namespace、use、类、方法、控制流、数组和较长调用不得压缩到一行；除空实现外，一个方法不得以单行实现。格式化基线由根目录 `.php-cs-fixer.php` 定义，提交前执行 `core/vendor/bin/php-cs-fixer fix --dry-run --diff`。
- 在需要解释设计取舍、安全边界、兼容性处理或不直观逻辑时添加简洁中文注释；不写重复代码含义的注释，也不为显而易见的赋值、循环或 getter 堆砌注释。
- 类型声明尽量完整。
- 优先 constructor injection。
- 避免 static service access。
- 避免超长类。
- 避免超长方法。
- 变量和方法名表达真实业务含义。
- 注释只解释“为什么”，不要重复代码本身。
- 不写看起来像模板化 AI 生成的大段无意义注释。

---

## 8. Database 规则

### 8.1 必须支持

- SQLite
- MySQL / MariaDB
- PostgreSQL

### 8.2 默认开发策略

日常单元/集成测试优先 SQLite，但关键数据库行为必须有 MySQL 和 PostgreSQL 测试。

### 8.3 禁止数据库锁定

Core 和官方 Plugin 默认禁止使用只适用于单个数据库的 schema 或 SQL。

谨慎使用：

- ENUM
- JSON 专属函数
- FULLTEXT 专属语法
- UPSERT 专属语法

如确实需要数据库差异，放入 Driver Adapter。

### 8.4 Migration

任何 Schema 修改必须通过 Migration。

禁止：

- 在普通请求中隐式建表
- Plugin boot 时偷偷改 schema
- 手工要求用户执行 SQL 文件

### 8.5 数据库迁移能力

数据结构设计必须允许未来：

- SQLite → MySQL
- SQLite → PostgreSQL
- MySQL → PostgreSQL

---

## 9. Content Model 规则

### 9.1 默认 Content Type

第一版：

- Article
- Page
- Product

三者必须尽量走同一套 Content API。

不要分别写三套 CRUD 基础设施。

### 9.2 Content Type 不绑定单独表

不要默认创建：

- articles
- pages
- products

所有通用 Content Type 优先共享统一 Content Storage。

特殊 Plugin 可以拥有自己的业务表。

### 9.3 动态字段

采用：

> 固定核心字段 + 动态数据 + 独立 Relation。

不要第一版采用纯 EAV。

### 9.4 Relation

Content 关系放独立 Relation 表，不要把核心关系全部塞 JSON。

### 9.5 Taxonomy

Category / Tag 使用通用 Taxonomy 系统，不要分别写死成两个完全不同的实现。

---

## 10. Field System 规则

Field 至少负责：

- normalization
- validation
- serialization
- deserialization
- admin rendering

Field 可以声明：

- required
- translatable
- default
- validation
- settings

Plugin 可以注册自定义 Field Type。

开发新 Field 时必须同时提供：

- 测试
- 文档
- 最小使用示例

---

## 11. 多语言规则

多语言不是后期插件。

从第一版数据模型开始支持：

- default locale
- enabled locales
- fallback locale
- translatable fields
- localized slug
- localized SEO fields

后台界面语言与 Content Translation 必须分开设计。

单语言网站不得被迫操作多语言 UI。

---

## 12. Theme / Layout / Template 规则

### 12.1 Theme

Theme 只负责：

- Layout
- Template
- Partial
- Component View Override
- Assets
- Theme Settings
- Theme Translation

### 12.2 Template

每条 Content 可覆盖 Content Type 默认 Template。

所有 Latte 模板（包括 Theme 的 Layout、Template、Component View 以及 Plugin、Core 提供的组件视图）必须使用 `.jst` 扩展名；不得新增或引用 `.latte` 文件。

解析顺序：

```text
Content Template
→ Content Type Default Template
→ Theme Fallback
```

### 12.3 Homepage

Homepage 必须通过后台选择 Page，不允许写死在 Router 或 Theme 中。

---

## 13. Component 规则

Component 是页面中的可复用展示/轻交互单元。

Component 可以：

- 接收参数
- 查询数据
- 渲染 View
- 缓存
- 由 Plugin 注册
- 被 Theme 覆盖 View

Component 不应承担大型业务状态机。

例如 `ExternalOrderFormComponent` 只负责表单和展示；真正下单由 `ExternalOrderService` 完成。

每个官方 Component 必须有：

- API 文档
- 参数说明
- Latte 使用示例
- 缓存说明
- Theme Override 说明

---

## 14. Plugin 规则

### 14.1 生命周期

至少：

- install
- enable
- disable
- upgrade
- uninstall
- register
- boot

### 14.2 可以注册

- Event Listener
- Route
- Middleware
- Service
- Component
- Field Type
- Content Type
- Admin Page
- Admin Menu
- Permission
- Migration
- Settings
- Translation
- CLI Command

### 14.3 禁止

Plugin 不得：

- 修改 Core 文件
- 修改 vendor
- 修改 Theme 原文件
- 依赖 Core Internal API
- 绕开 Permission
- 绕开 Migration
- 静默向外部服务发送用户数据

### 14.4 Plugin 文档

每个官方 Plugin 至少提供：

- README
- 安装说明
- 配置说明
- 事件/路由说明
- 数据表说明（如有）
- 升级说明
- 卸载行为说明

---

## 15. Event 规则

事件使用对象，不使用无约束魔法字符串 Hook。

事件命名使用过去/进行状态明确区分，例如：

- ContentCreating
- ContentCreated
- ContentPublishing
- ContentPublished

Listener 不应修改与事件无关的全局状态。

---

## 16. Admin 规则

后台技术：

- Server-side rendering
- Tailwind CSS
- Alpine.js

第一版禁止为了“现代”而做大型 SPA。

Admin 目标：

- 快
- 简单
- 可维护
- 可扩展

后台必须采用清晰的 MVC 架构：

- Model 使用既有的领域对象、Repository 与 Service，负责数据和业务规则；不得将 SQL 或业务规则写入 Controller。
- Controller 仅负责路由参数、认证与权限、CSRF、请求输入协调、调用 Service，以及返回 Response；不得拼接大段 HTML 或承担 View 逻辑。
- View 负责服务端 HTML 渲染，统一使用 Latte `.jst` 模板；Layout、页面 View 与可复用 Partial 分离。
- View 只接收已准备好的展示数据，不直接访问数据库、Container 或执行写操作。
- Admin 的表单字段仍由 Field Definition / Field Type 渲染；Controller 不为具体 Field 编写展示分支。

Content 表单必须尽量由 Field Definition 自动生成。

后台表单必须复用统一的视觉层级：页面标题与说明、白色圆角卡片、`space-y-6` 字段间距、清晰的 label/help/error，以及底部带分隔线的操作区。字段较多或包含两个及以上独立主题时，必须按主题拆分为可访问的 Tab；Tab 只负责界面分组，不得拆散同一次提交所需的服务端校验。

Content Field Type 负责内容模型字段的渲染与业务规则；系统设置等非 Content 表单不得伪装成 Content Field。它们仍必须复用后台全局控件基线（边框、内边距、圆角、焦点状态）或通用表单 Partial，禁止在单个页面重新发明控件样式。

---

## 17. Installer 规则

普通用户安装方式：

```text
/install
```

不要求 CLI。

Installer 必须完成：

1. 环境检查
2. Database 选择
3. Site Settings
4. Admin 创建
5. Migration
6. Installation Lock

默认数据库选择 SQLite。

安装完成后必须安全禁用 Installer。

---

## 18. Core Update 规则

核心升级必须从第一版架构考虑。

### 18.1 不允许破坏扩展目录

升级不得覆盖：

- site/
- plugins/
- themes/
- storage/
- 用户配置

### 18.2 升级流程

至少：

1. Compatibility Check
2. Backup
3. Maintenance Mode
4. Update Package Verification
5. Core Replacement
6. Migration
7. Cache Clear
8. Health Check
9. Rollback on failure

### 18.3 Public API

任何 Plugin 可依赖的接口必须标注为 Public API。

不要让官方示例依赖 Internal Class。

---

## 19. Database Admin Tools

后台必须逐步支持：

- Database info
- Migration status
- Integrity check
- Optimization
- Cleanup
- Backup
- Database migration

Driver-specific 行为封装到 Adapter。

---

## 20. SEO 规则

基础 SEO 属于 Core：

- slug
- title
- meta description
- canonical
- robots
- Open Graph
- Sitemap
- Redirect
- Breadcrumb
- hreflang
- Schema extension hook

高级 SEO 属于 Plugin：

- SEO Audit
- Broken Links
- Orphan Pages
- Internal Link Suggestions
- Search Console
- SERP Tracking

---

## 21. Security 规则

开发任何后台或写操作必须检查：

- Authentication
- Permission
- CSRF
- Validation
- Output escaping

后台中任何危险操作必须提供二次确认，包括但不限于删除内容、清除缓存、清除日志、删除媒体、卸载或停用 Plugin、清理数据与不可逆覆盖操作。确认提示必须说明将要影响的对象；前端确认不替代服务端的 Authentication、Permission、CSRF 与输入校验。

上传必须验证：

- MIME
- Extension
- Size
- Destination

日志不得记录：

- Password
- API Secret
- Session token
- CSRF token
- Sensitive credentials

---

## 22. 文档同步规则

文档不是最后再补。

开发任意公共能力时必须同步更新对应文档。

目录至少包括：

```text
docs/
├── installation/
├── upgrade/
├── configuration/
├── content-types/
├── fields/
├── themes/
├── layouts/
├── templates/
├── components/
├── plugins/
├── events/
├── routing/
├── database/
├── multilingual/
├── seo/
└── api/
```

新增公共 Extension Point 时，如果没有对应文档，该功能不视为完成。

---

## 23. 测试规则

### 23.1 必须测试

- Unit
- Integration
- Installer
- Database
- Migration
- Content Type
- Field
- Relation
- Taxonomy
- Theme resolution
- Component
- Plugin
- Multilingual
- Update
- Rollback

### 23.2 跨数据库

关键数据测试覆盖：

- SQLite
- MySQL
- PostgreSQL

### 23.3 Bug 修复

修复 Bug 前尽量先添加失败测试，再修复。

### 23.4 Release

每个 Stable Release 至少测试：

```text
Previous Stable → New Stable
```

---

## 24. 开发阶段

不要跨阶段一次性生成全部系统。

### Phase 1：Skeleton

实现：

- Composer project
- Core directories
- Bootstrap
- Config
- Container
- HTTP
- Router
- Logging

完成标准：

- 首页可返回 Response
- 错误处理成立
- 基础测试成立

### Phase 2：Database + Installer

实现：

- DB abstraction
- Migration
- `/install`
- SQLite default
- User
- Auth
- Installation lock

完成标准：

- 新环境只访问 `/install` 可完成安装

### Phase 3：Content Core

实现：

- Content Type
- Field
- Content storage
- Translation storage foundation
- Relation

完成标准：

- Article / Page / Product 共用统一 Content API

### Phase 4：Admin CRUD

实现：

- Admin shell
- Dynamic content forms
- List / Create / Edit / Delete / Publish

完成标准：

- 新增 Field 后无需修改 Controller 即可出现在表单

### Phase 5：Theme System

实现：

- Latte
- Theme
- Layout
- Template
- Homepage selection

完成标准：

- 同一 Content Type 的不同 Content 可选不同 Template

### Phase 6：Component

实现：

- Component registry
- Component renderer
- Parameters
- Theme override

至少实现：

- Breadcrumb
- RelatedPosts

### Phase 7：Plugin

实现：

- Discovery
- Manifest
- Lifecycle
- Public Extension API
- Events
- Routes
- Services
- Admin extensions
- Plugin migration

完成标准：

- Plugin 可在不改 Core 的情况下增加 Component + Route + Settings

### Phase 8：ExternalOrderPlugin

实现完整验证链：

```text
Product
→ ExternalOrderForm Component
→ Plugin Route
→ Validation
→ Service
→ Fake Third-party API
→ Result
```

任何一步都不得修改 Core。

### Phase 9：Taxonomy + Media

实现：

- Taxonomy
- Terms
- Media Library
- Image metadata

### Phase 10：SEO Foundation

实现：

- Metadata
- canonical
- robots
- sitemap
- redirect
- breadcrumb
- schema hook

### Phase 11：Multilingual UI

完善：

- Admin interface translations
- Content translation UI
- Locale URL
- hreflang
- localized sitemap

### Phase 12：System Tools

实现：

- Core update
- Backup
- Database tools
- Cache tools
- Logs
- System info

### Phase 13：Documentation Hardening

整理所有已同步生成的文档，补齐：

- Theme guide
- Template guide
- Component guide
- Plugin guide
- Content Type guide
- Field guide
- Upgrade guide
- Database guide

### Phase 14：Release Hardening

- Security review
- Performance baseline
- Upgrade test
- Cross-db CI
- Documentation validation
- Release package

---

## 25. MVP 不允许提前实现

除非当前阶段明确要求，否则不要开发：

- Full Ecommerce
- Payment
- Inventory
- Community
- Membership
- Points
- Queue requirement
- Redis requirement
- Elasticsearch
- GraphQL
- Large REST API
- Multi-site
- Drag-and-drop Builder
- Visual Low-code Builder
- Marketplace
- AI
- Complex Workflow
- Realtime Collaboration

---

## 26. 代码审查自检

提交每个阶段前检查：

- 是否修改了不该修改的 Core 边界？
- 是否新增了不必要依赖？
- 是否为了一个场景做了过度抽象？
- 是否绑定单一数据库？
- 是否破坏多语言预留？
- 是否引入 Site-specific 逻辑到 Core？
- 是否把行为误做成 Content Type？
- 是否把展示块误称为 Module？
- 是否应该做 Component 而不是 Plugin？
- 是否应该做 Plugin 而不是 Core？
- 是否有测试？
- 是否同步文档？
- 是否影响 Core Upgrade？

---

## 27. Definition of Done

一个功能只有同时满足以下条件才算完成：

1. 实现正确。
2. 边界符合 `ARCHITECTURE.md`。
3. 有必要测试。
4. 不破坏 SQLite / MySQL / PostgreSQL 兼容目标。
5. 不破坏 Core Upgrade。
6. 多语言影响已考虑。
7. 安全检查完成。
8. 文档已更新。
9. 示例或 API 用法足够清楚。
10. 没有为了未来假想需求引入明显过度设计。

---

## 28. 最终准则

> Core 提供能力，不规定业务。

> Content Type 描述数据，Plugin 扩展行为。

> Component 是页面中的可复用展示/轻交互单元，不使用 Module 表示该概念。

> Theme 决定整体视觉，Template 决定 Content 如何显示。

> 普通用户通过 `/install` 完成安装。

> Core 必须可以升级，Site / Plugin / Theme / Data 不依赖修改 Core。

> 文档、测试、多语言、SEO 和升级能力不是项目完成后的补充，而是开发过程的一部分。
