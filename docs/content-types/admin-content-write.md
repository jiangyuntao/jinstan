# 后台 Content 写入流程

创建内容使用 `CreateContent` 应用用例。Controller 先通过 `ContentRequest` 验证请求，随后将上传文件数组、已验证输入、当前语言、操作者 ID 和完整 HTML 页面权限交给用例。Application 不接收完整 HTTP Request；用例调用 `ContentAttachmentCoordinator` 准备附件，再由 `ContentService::saveWithTerms()` 在数据库事务中保存 Content、翻译和 Taxonomy 关系。

当操作者无权保存完整 HTML 页面时，用例抛出 `ContentOperationDenied`。Controller 将该应用异常映射为 HTTP 403；应用层本身不依赖 HTTP 状态码或 HTTP 异常。

上传文件已保存但后续权限检查或业务保存失败时，用例清理本次新增且未被其他内容引用的附件。权限不足抛出 `AuthorizationException`，Controller 返回 403；其余表单错误仍回显为 422。

更新内容使用 `UpdateContent` 应用用例。它从已有 Content 与当前语言翻译合并字段值，再准备附件并调用同一 `ContentService::saveWithTerms()`。数据库保存成功后才清理已替换、且不再被引用的旧附件；保存失败时只清理本次新上传的附件，原内容和旧附件保持不变。

删除内容使用 `DeleteContent` 应用用例。对象不存在或 Content Type 不匹配时返回 404；数据库中的内容、翻译和关联删除在同一事务中完成。只有数据库提交成功后，才清理不再被引用的附件。数据库删除失败会回滚内容及翻译，并保留文件。
