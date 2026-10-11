# 后台 Content 列表查询

`ContentListQuery` 负责后台 Content 列表的内容读取、当前语言翻译回退、可见列搜索、状态筛选和分页。未找到当前语言翻译时，先使用站点默认语言，再尝试 `zh-CN`，最后使用其他可用翻译。返回的 `translated` 标记始终表示当前语言是否有独立翻译，不因回退而变为真。

搜索只覆盖用户当前选择的可见列；列表默认按内容 ID 倒序。没有关键词时，`ContentRepository::adminPage()` 已在数据库内完成类型与状态筛选、总数统计和分页，再为当前页读取翻译。关键词搜索需要保持多语言回退与可见列语义，目前仍在 PHP 中筛选全部内容；后续再评估跨数据库下推。

`ContentListPresenter` 负责可见列选项、翻译来源名称、行操作 URL、列标题和分页导航。Controller 只协调请求参数、查询与页面响应；Presenter 不执行数据库查询或写操作。

列表查询参数只接受文本搜索词与状态、正整数页码以及字符串列名。异常嵌套值安全回退为空筛选或第一页，不会触发 PHP 数组转字符串警告。

内容表单另由 `ContentFormPresenter` 准备 Field Type 渲染数据、模板选项、Taxonomy 回显、父内容选项以及缺失当前翻译时的源语言参考。Controller 仍负责认证后的请求协调和保存响应；Presenter 不执行写操作。

表单的父内容联想和 Taxonomy 条目联想由 `ContentSearchQuery` 查询。父内容仅对支持层级的 Content Type 开放；条目搜索仅对该类型已绑定的 Taxonomy 开放。Controller 保留路由错误的 404 与 JSON 响应，不直接拼接查询。

编辑表单的初始值由 `ContentEditQuery` 读取：共享字段与当前语言字段合并；若当前语言尚无翻译，标题和 Slug 留空，由 `ContentFormPresenter` 另行提供源语言参考。内容不存在或类型不匹配时返回空结果，Controller 映射为 404。
