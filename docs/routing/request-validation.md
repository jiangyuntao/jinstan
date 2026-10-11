# Request Validation

HTTP 输入校验使用 `Jinstan\Core\Http\Validation` 下的小型边界，目标是让 Controller 只把已 normalization 且通过校验的数据交给业务服务。

## Validator 与 ValidationResult

`Validator::validate()` 接收未信任输入数组，返回 `ValidationResult`。Result 同时携带 normalization 后的数据和按字段分组的错误；调用 `validated()` 时，有错误则抛出 `ValidationException`，否则只返回可信数据。

JSON 请求会由统一 `ExceptionHandler` 返回：

```json
{
  "message": "表单验证失败。",
  "errors": {
    "email": ["邮箱格式无效。"]
  }
}
```

## ActionRequest

`ActionRequest` 是固定结构表单的基类，负责从 PSR-7 Request 安全提取 Parsed Body，并提供只接受字符串或标量值的字符串 normalization 辅助方法。数组、对象等非预期值不会被强制转换为字符串。

Login 是第一个迁移对象：`LoginRequest` 对邮箱 trim，但保留密码原始空格。CSRF 是请求安全边界，不属于业务字段校验，因此仍在进入 `LoginRequest` 前处理。

Role 表单也已迁移。`RoleRequest` 负责角色标识、名称和权限列表的格式，权限列表仅接受字符串并去重。依赖数据库状态的权限白名单、内置管理员角色和角色标识不可修改规则继续属于 `RoleRepository`。验证失败时 Controller 只使用 `ValidationResult::normalized()` 回显，写操作前必须先调用 `validated()`。

User 管理和个人资料共用 `UserRequest`，但通过明确的创建、更新与资料场景保持不同边界：创建要求至少 6 字符密码，更新和资料表单可留空以保持原密码，但填写新密码时仍需至少 6 字符。资料表单不接受角色输入。角色是否存在、最后一名管理员保护和当前用户不能自我降权属于数据和业务规则，不放入 Request Validator。

System Settings 的固定标量字段已迁移到 `SystemSettingsRequest`，包括站点名称和说明文本、联系邮箱、前台与后台 Locale、Theme、预定义 Homepage/404 页面、保留路径、时区、日期格式、每页内容数、排序方式、默认发布状态和活动 Tab。日期格式、后台 Locale、预定义页面定义与 Tab 选项使用 Request 上的共享白名单，页面选项与服务端校验不会漂移；前台 Locale 必须属于已启用的 Content Locale。Theme 列表与首页模板可用性通过 `ThemeSelectionGateway` 查询，Request 不创建适配器或访问文件系统。预定义页面 ID 仅接受非负整数，大于 0 时必须指向 Page Content，数组等异常输入不会被强制转换。保留路径必须是标量文本，Request 会逐行转小写、移除尾斜杠、校验路径字符并去重。表单未提交 Content 默认值时，Request 从 `SettingsRepository` 读取已保存值，避免部分提交把配置重置为硬编码默认值。

动态 Content Field 仍由 Field Type 负责 normalization、validation、serialization 和 deserialization，不会被通用 ActionRequest 重复实现。

Content 创建和更新现使用 `ContentRequest` 检查标题、Slug、动态字段容器、分类容器、模板和上级内容的请求形状。标题与 Slug 去除首尾空格；`data` 内的字段原值不被通用请求层改写。格式错误返回 422 且不会先保存上传附件；具体 Field 值、分类合法性和模板可用性仍由各自业务边界校验。

Content 表单提交的 `locale` 必须属于已启用内容语言；发布状态只接受布尔形式的 `published`。无效值在请求层返回 422。创建时未提交 `published` 则使用系统默认状态；更新时未提交表示取消发布。

媒体字段上传由 Application 的 `ContentUploadInput` 统一整理 PSR-7 上传文件：空文件槽跳过，异常上传错误、非文件结构和单文件字段收到多个文件时拒绝。文件内容的大小、MIME、扩展名与存储目标仍由附件存储适配器检查；失败时用例清理此次新建但未被引用的附件。

媒体删除、管理标记和排序令牌的外层结构由 `ContentRequest` 检查。非法排序值不会被静默视为空列表，以免误移除现有附件；令牌是否属于当前内容仍由附件处理逻辑判断。

上传处理从第一份成功写入的附件开始追踪本次新增路径。后续文件验证失败、内容保存失败，或完整 HTML 页面因权限不足返回 403 时，Controller 会清理本次新增且未被其他内容引用的附件；已被其他内容复用的相同哈希文件不删除。

上传数据合并、替换路径记录、删除前引用检查与失败清理现集中在 `ContentAttachmentCoordinator`。Controller 负责调用它并根据内容保存结果选择清理新增路径或旧路径；实际文件大小、MIME、扩展名与存储仍由 `ContentAttachmentStorage` 负责。

SEO 设置也由 `SystemSettingsRequest` 返回已验证的设置键映射。`robots` 只允许明确白名单；标题模板限制为 255 字符，并仅允许 `site_name`、`tagline`、`title`、`content_type`、`page`、`page_suffix` 和 `separator` 占位符。标题分隔符的前后空格属于展示内容，特意不做 trim。

SNS 地址使用 `SnsPlatforms::GROUPS` 的平台清单。未提交的平台沿用已保存设置；提交值必须是字符串，允许留空，非空时必须是完整 HTTP 或 HTTPS URL。Request 返回 `site.sns.*` 设置键映射；数组等异常输入不会被强制转换。

自定义设置的 `custom_keys[]` 和 `custom_values[]` 由 Request 成对处理，最多 100 项。空键行忽略；非空键修剪后须以小写字母开头且只含小写字母、数字、点、连字符、下划线，不得重复。值保留原始空格，最多保存 10000 字符。嵌套数组或错误类型返回 422；验证通过后才由 `SettingsRepository::replaceCustom()` 替换整组设置。

System Settings 的普通设置与整组自定义设置由 `SettingsRepository::saveSystem()` 在同一数据库事务中提交。任一数据库写入失败时回滚全部变更；独立调用 `replaceCustom()` 的既有公共行为不变。
