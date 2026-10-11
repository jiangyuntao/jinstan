# ThemeServiceProvider

## 职责

`Jinstan\Core\Providers\ThemeServiceProvider` 负责已安装站点的 Theme 展示基础设施装配：

- `Infrastructure\\Persistence\\Navigation\\NavigationRepository`
- `ComponentRegistry`
- `Infrastructure\\View\\TemplateCatalog`
- `ContentService`
- `ThemeRenderer`
- `Infrastructure\\Persistence\\SEO\\RedirectRepository`
- `SchemaRegistry`
- `PublicContentController`

它依赖 Database 和 Content Provider 已注册的 Connection、Settings、Content Type Registry、Content Repository 与 Taxonomy Repository。

## 核心 Component

Provider 注册以下 Core Component：

- Breadcrumb。
- RelatedPosts。
- LatestContent。
- NavigationMenu。

Component Registry 后续仍可由 Plugin 扩展。Component 只承担展示或轻交互，复杂业务流程必须委托 Application Service。

## ContentService 装配

`ContentService` 同时需要 Content 数据能力、Template Catalog 和 Protected Slug 策略，因此在 Theme 边界建立后才从 Bootstrap 迁出。

这不改变 Content 的领域归属。Provider 只是当前 Composition Root 的拆分单位，Theme 不获得修改 Content 业务规则的权力。

## Theme 路径安全

活动主题来自 `site.theme` 设置。Provider 必须验证：

- `themes/` 根目录真实存在。
- 活动主题目录真实存在。
- 解析后的真实路径仍位于 `themes/` 根目录内。

无效、缺失或试图跳出目录的主题标识统一回退到 `themes/default`。该检查已从 Bootstrap 原样迁移，并增加路径逃逸回归测试。

## 未安装状态

Container 中没有 Connection 时，Provider 不注册 Theme、Component 或前台 Content 服务。Installer 不依赖 Theme Provider。

## 路由边界

Theme Provider 当前不注册 HTTP Route。Homepage、Content、Locale 和 Sitemap 路由继续由现有 Bootstrap 注册，直到 RouteServiceProvider 阶段统一迁移。

Theme 本身不得注册订单、支付或第三方 API 等业务路由。

## 测试

`tests/Integration/ThemeServiceProviderTest.php` 覆盖：

- 未安装状态不注册 Theme 服务。
- 已安装状态注册完整 Theme、Component 和 Content 展示服务。
- PublicContentController 可以渲染首页。
- 非法主题路径回退到内置默认主题。
