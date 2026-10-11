# ContentServiceProvider

## 职责

`Jinstan\Core\Providers\ContentServiceProvider` 负责已安装站点的 Content 基础设施注册：

- `SettingsRepository`
- `ContentTypeRegistry`
- `Application\\Content\\Services\\EditableContentTypes`
- `Infrastructure\\Persistence\\Content\\ContentRepository`
- `Infrastructure\\Persistence\\Taxonomy\\TaxonomyRepository`
- `Infrastructure\\Storage\\Content\\ContentAttachmentStorage`

Provider 首先注册 Article、Page、Product 三个默认 Content Type，再从设置中加载站点自定义 Content Type。三种默认类型仍共用统一 Content Storage 和 Content API。

## 安装边界

Content Provider 依赖 Database Provider：

```text
FoundationServiceProvider
→ DatabaseServiceProvider
→ ContentServiceProvider
```

Container 中不存在 `Connection` 时，Content Provider 不注册任何数据库相关服务。这使 `/install` 可以在未配置数据库的环境运行。

## 当前迁移边界

本阶段只迁移稳定的数据与注册表能力。以下内容暂时不属于 Content Provider：

- Theme Path 和 `TemplateCatalog`。
- `ThemeRenderer`。
- Component Registry。
- Content 的 HTTP Route。
- Admin Controller。

当前 `ContentService` 依赖 Template Catalog 和 Protected Slug 策略，等 Theme Provider 提供明确边界后再从 Bootstrap 迁移，避免 Content Provider 反向承担 Theme 职责。

## 复用规则

Bootstrap、Admin 和后续 Provider 必须复用 Container 中已注册的 ContentRepository 与 TaxonomyRepository，不得为每个 Controller 重复创建实例。

这不是让业务代码通过 Container 定位服务。Container 访问仅允许出现在 Composition Root 和 Service Provider；Controller 仍然使用构造器注入。

## 扩展边界

本 Provider 和上述具体 Repository 当前属于 Core Internal。Plugin 不得直接访问 Container 获取它们。正式 Public API 将在 Repository Contract 与 Plugin Framework 阶段定义。

## 测试

`tests/Integration/ContentServiceProviderTest.php` 验证：

- 未安装状态不会注册 Content 数据服务。
- 已安装站点注册统一的 Content、Taxonomy、Settings 和附件服务。
- Article、Page、Product 仍由同一 Registry 提供。
