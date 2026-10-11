# Component View 解析

`Infrastructure\\View\\ComponentViewLocator` 只负责定位 Component 的 `.jst` View，不执行组件业务逻辑。解析顺序固定为：

1. 当前 Theme：`themes/<theme>/components/<name>.jst`
2. Plugin：`plugins/*/views/components/<name>.jst`
3. Core：`core/resources/views/components/<name>.jst`

Theme 因此可以覆盖 Core 或 Plugin 的 Component 展示，而不修改其 PHP 类。覆盖文件接收的数据与原 Component View 相同。

Component 名称必须以小写字母开头，只能包含小写字母、数字和连字符。Locator 会拒绝斜杠、反斜杠和 `..`，避免组件名称逃逸到允许的 View 目录之外。

多个 Plugin 提供同名 View 时，按完整文件路径的字典序选择第一个，以保证不同文件系统上的结果稳定。Plugin 应使用唯一 Component 名称避免冲突；后续带归属信息的 Component 注册 API 可以进一步消除同名候选。

找不到 Theme、Plugin 或 Core View 时，渲染会明确抛出缺失 View 异常。Component View 不应访问 Container、数据库或执行写操作；数据查询和缓存由 Component 类负责。
