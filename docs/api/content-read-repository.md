# Content Read Repository

`Jinstan\Core\Content\ContentReadRepository` 是前台展示与只读 Application Query 可依赖的 Public API。

首版契约只包含已经稳定使用的读取能力：

- `find()`：按 ID 读取 Content。
- `list()`：按 Content Type 读取内容集合。
- `translation()` / `translations()`：读取单一或全部翻译。
- `publishedPage()`：读取指定 Locale 的已发布分页数据。
- `findBySlug()`：按 Content Type、Locale 和 slug 读取已发布 Content。
- `publishedSlugs()`：读取 Sitemap 等索引场景所需的已发布 Content Type、Locale 与 slug；不暴露 SQL 行为。

契约不暴露数据库 Connection、SQL、Query Builder 或 Driver-specific 类型，也不包含写入、事务、后台搜索等尚未稳定的能力。

Core 当前使用 `ContentRepository` 作为数据库持久化实现，并由 `ContentServiceProvider` 将同一实例同时绑定到具体类和读取契约。前台首页、详情、列表、404、Sitemap 与 Dashboard Query 只依赖该契约。

Plugin 如只需读取 Content，必须依赖此接口。具体 `ContentRepository` 属于 Internal API，官方 Plugin 的架构测试会拒绝这种依赖。

## 写入与组合契约

`ContentWriteRepository` 只包含创建、更新、保存翻译、设置发布状态、删除和事务协调。它不包含后台搜索、分页或 Relation API。

同时需要读取与写入的 Application Service 使用 `ContentRepositoryContract`，该组合接口继承读写两个最小契约。当前 `ContentRepository` 实现组合契约，Provider 将三个接口绑定到同一数据库适配器实例。

## 管理查询

`ContentAdminReadRepository` 在只读契约上增加 `adminPage()` 和 `searchTitles()`，供后台列表与父级内容选择器使用。它属于 Internal Extension API，不应作为普通 Plugin 的首选依赖。

## Relation

Relation 能力独立为：

- `ContentRelationReadRepository::related()`。
- `ContentRelationWriteRepository::relate()`。

Related Posts Component 只依赖 Content 读取与 Relation 读取的交集，不获得 Relation 写入权限。Relation 继续存放在独立表中，没有塞入 Content 动态数据。
