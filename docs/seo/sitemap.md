# Sitemap

`/sitemap.xml` 输出所有启用 Content Type 的列表地址，以及已发布 Content 的可用翻译地址。

`SitemapQuery` 负责读取已发布内容、启用 Locale、默认 Locale 和站点 URL，并通过 `ContentUrls` 生成去重后的路径集合。未注册 Content Type 和未启用 Locale 的翻译不会进入 Sitemap。

`SitemapPresenter` 只负责将绝对地址编码为 Sitemap XML，所有地址均使用 XML 转义。`SitemapController` 仅协调 Query、Presenter 和带 `application/xml; charset=utf-8` 的 Response。

当前 Sitemap 不写入文件，也不在普通请求中修改数据库或缓存。
