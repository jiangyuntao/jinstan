# Field 开发指南

Field Type 负责 normalization、validation、serialization、deserialization 与后台渲染。Content Controller 不应按具体 Field Type 编写分支。

通过 `PluginRegistrar::fieldTypes()` 注册自定义 Field Type，并同时提供测试、配置说明和最小内容类型示例。字段定义可以声明 `required`、`translatable`、默认值、校验规则及类型设置。

Field 的存储格式必须保持跨 SQLite、MySQL/MariaDB 和 PostgreSQL 可移植；数据库专属 JSON 查询不得进入公共 Field API。
