# 后台分类法条目列表

`TaxonomyTermListQuery` 负责后台分类法条目列表的只读查询，包括 Taxonomy 存在性检查、搜索词和页码规范化、Locale 解析、分页，以及目标语言缺少翻译时的来源翻译补充。

顶层词汇表清单由 `TaxonomyIndexQuery` 查询。`TaxonomyListPresenter` 接管顶层列表和 Term 列表的模板 View Model，包括未翻译占位文案、搜索上下文、语言选项和上一页／下一页命名路由。Controller 只选择查询、处理未找到响应并渲染 Presenter 结果。

来源翻译由 `TaxonomyTranslationResolver` 统一选择。它优先使用站点默认语言，其次使用系统回退语言，再使用该条目其他可用语言；目标语言本身不会被当作回退来源。表单中的来源翻译提示也复用同一解析器，避免列表和编辑页选择不同的参考语言。

Query 返回普通数组，不渲染 HTML，也不生成 URL。`TaxonomyAdminController` 仍负责 HTTP 404 和模板响应。

分页内拖动排序由 `ReorderTaxonomyTerms` 命令处理。命令只接受明确存在的 Taxonomy、最多 20 个正整数 Term ID 和大于等于 1 的页码；Term 必须属于目标 Taxonomy，重复 ID 也会被拒绝。位置计算和事务写入继续由 `TaxonomyRepository::reorder()` 负责。

Taxonomy 与 Term 表单的创建、更新和删除由 `SubmitTaxonomyForm` 协调。删除必须收到明确确认；删除 Taxonomy 时仍通过 `ContentTypeRegistry` 检查运行期绑定，并由 Repository 检查持久化绑定、现有 Term、父子关系等领域约束。Controller 只准备回显值，将领域异常转换为 422 表单响应。

表单读取由 `TaxonomyFormQuery` 完成；它统一加载 Taxonomy 和指定 Locale 的 Term 数据，不存在时向 Controller 抛出明确的未找到异常。`TaxonomyFormPresenter` 负责标题、语言选项、来源翻译提示、错误和返回地址等 View Model，模板只接收准备好的展示数据。
