# AdminServiceProvider

## 职责

`Jinstan\Core\Providers\AdminServiceProvider` 负责已安装站点的后台对象装配。它统一创建并注册：

- `Authenticator`
- Admin `Translator`
- `AdminViewRenderer`
- `AdminContentForm`
- User、Role、Backup 与 Content Type 删除支持服务
- Content 列表／编辑／搜索 Query、列表／表单 Presenter、附件协调器及创建／更新／删除用例
- Login、Content、Content Type、Taxonomy、Navigation、Theme、Database、User、Role、Settings、Language、Cache 和 Logs Controller

Controller 仍通过构造器接收依赖。Container 只在 Provider 和当前 Bootstrap Composition Root 中使用，Controller 本身不访问 Container。

`ContentAdminController` 的协作者均由 Provider 作为共享实例注册并注入，Action 内不直接构造 Query、Presenter 或写入用例。

## Provider 顺序

Admin Provider 依赖前置 Provider：

```text
Foundation
→ Database
→ Content
→ Theme
→ Admin
```

没有已配置 Connection 时，Admin Provider 不注册认证、View 或后台 Controller。未安装站点仍只开放 Installer 所需能力。

## 共享基础服务

Admin Provider 复用前置 Provider 中的：

- Connection。
- SettingsRepository。
- ContentTypeRegistry。
- ContentRepository。
- TaxonomyRepository。
- ContentAttachmentStorage。
- NavigationRepository。
- TemplateCatalog。
- ContentService。
- Router 与 CsrfToken。

这消除了 Bootstrap 为不同 Controller 重复创建 Repository、CSRF 和辅助服务的行为。

## Provider 边界

`PluginManager` 与 `PluginAdminController` 由 `PluginServiceProvider` 装配，Admin Route 由 `RouteServiceProvider` 注册。Authentication、Permission 与 CSRF 由明确的 Middleware Group 处理；Controller 只保留资源级业务保护。

## View

Admin View 继续使用现有 `.jst` 模板目录和 Latte 缓存目录。Provider 只装配 Renderer，不移动模板、不改变 HTML 输出。

## 测试

`tests/Integration/AdminServiceProviderTest.php` 验证：

- 未安装状态不注册 Admin 服务。
- 已安装状态注册认证、Renderer 和代表性 Controller。
- Content Query、Presenter、附件协调器及写入用例均可从 Container 解析。
- Provider 创建的 LoginController 可以渲染登录表单。
- 完整路由快照和 HTTP 安全基线保持不变。
