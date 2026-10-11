# 后台导航管理

`NavigationListQuery` 负责后台导航页的只读数据：菜单清单、当前菜单、Content Locale、菜单项及缺失翻译的来源参考。未明确选择菜单时使用按名称排序后的第一个菜单；异常的嵌套菜单参数不会被强制转换为字符串。

`NavigationListPresenter` 负责列表 View Model 的展示补充，包括完全没有可用翻译时的占位文案。Query 和 Presenter 都不渲染 HTML。

`NavigationTranslationResolver` 统一列表与编辑表单的来源翻译规则。它优先选择站点默认语言，其次选择系统回退语言，再选择其他可用翻译，并排除当前目标语言。这样列表中的“未翻译”提示和编辑页的原文参考始终一致。

`NavigationItemFormQuery` 负责菜单项表单的只读 View Model：当前 Locale 的菜单项、所属菜单、语言选项、来源翻译及排除自身后的父级候选。编辑不存在的菜单项、创建时缺失标量菜单标识都会转换为 404，不会将嵌套参数强制转换为字符串。

Query 只返回普通数组，不渲染模板、不执行写操作。创建、更新、层级校验和排序仍由后续 Application Command 与 `NavigationRepository` 边界负责。

菜单和菜单项写入由 `NavigationWriter` 协调。它规范化并验证菜单标识、名称、链接、Locale、打开方式、位置、父级 ID 和完整排序集合，再调用 `NavigationRepository`。Repository 继续负责菜单存在性、父级同菜单约束、循环引用保护、目标白名单与排序事务。

嵌套数组等异常标量输入不会被 PHP 强制转换；它们会在写入前返回验证错误。删除操作仍需经过后台 Authentication、Permission、CSRF 和前端明确的对象影响确认。

Controller 只向用户返回可操作的领域验证信息。数据库约束异常使用固定的 422 提示，其他未预期异常返回通用 500 响应，不向页面泄露 SQL 或驱动错误细节。
