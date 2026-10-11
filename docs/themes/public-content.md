# 前台 Content 展示边界

前台 Content Controller 按 Query、Presenter 和 HTTP Response 分层迁移，Theme 仍只负责最终展示。

## 首页

`PublicHomepageQuery` 负责：

- 解析并校验请求 Locale。
- 读取后台选择的预定义首页。
- 对预定义 Page 应用发布状态与翻译回退规则。
- 未使用预定义首页时，按 Content Type 构建首页内容分区。
- 使用 `ContentUrls` 生成列表和详情路径，并生成绝对 canonical。

`PublicContentPresenter` 将 Query 结果交给 `ThemeView` 契约。默认实现是 Infrastructure 的 Latte `ThemeRenderer`。预定义 Page 使用 `homepage` 页面语境渲染；主题默认首页继续接收内容分区、Locale 和 canonical。

`PublicContentController` 只负责将无效 Locale 转交 404 流程，以及返回 HTML Response。详情、列表和 404 将沿同一边界继续迁移。

## 详情与列表

`PublicContentQuery` 负责详情 slug 查询与 fallback、历史路径重定向、hreflang 链接、详情 canonical，以及列表分页、排序、Locale fallback 和前后页链接。`PublicContentPresenter` 将准备好的详情或列表 View Model 交给 `ThemeView`。

Controller 只处理 not found、301 redirect 和 HTML Response；不再查询详情或列表 Content，也不再组装分页和多语言链接。

## 404

`PublicNotFoundQuery` 根据路由参数或请求路径选择有效 Locale，读取后台配置的 404 Page，并校验类型、发布状态和翻译 fallback。Query 同时构建当前错误路径的 canonical 与可用翻译 hreflang 链接。

未配置有效 Page 时 Controller 返回纯文本 404；有效 Page 由 Presenter 使用 `not_found` 语境渲染。两种响应都保持 404 状态，自定义页面继续发送 `X-Robots-Tag: noindex`。

该拆分不改变 Locale fallback、Content 排序、每个首页分区最多六条内容、分页、canonical、hreflang 或 Theme 模板解析规则。
