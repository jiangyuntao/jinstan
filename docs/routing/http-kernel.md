# HTTP Kernel 与 Middleware

## 请求主链

当前所有 Web 请求统一经过：

```text
public/index.php
→ Foundation Application
→ Http Application
→ Http Kernel
→ Middleware Pipeline
→ Router Request Handler
→ Controller / Not Found Handler
```

`Http\Application` 是 PSR-7 请求入口，实际请求调度、Middleware 和异常捕获由 `Http\Kernel` 负责。

## Middleware 契约

Middleware 实现 `Jinstan\Core\Http\Contracts\Middleware`，并通过 `process()` 接收当前 Request 和下一个 Request Handler。

Middleware 按注册顺序进入，按相反顺序返回。Middleware 可以不调用下一个 Handler，直接返回 Response 实现短路。

当前内置 Middleware：

- `MaintenanceModeMiddleware`：位于全局管线最外层，使用 `Infrastructure\\Filesystem\\Update\\MaintenanceMode` 检查维护锁；锁存在时在启动 Session 或分发路由前返回可重试的 503 响应，只读 `/health` 健康检查例外。
- `StartSessionMiddleware`：在 Router、认证和 CSRF 前启动 PHP Session。
- `AdminLocaleMiddleware`：已安装站点在请求开始时解析后台界面语言，并更新显式注入的 Admin Locale 上下文。
- `AdminSecurityMiddleware`：统一保护 `/admin` 请求；浏览器访客跳转到登录页，JSON 访客获得 401；后台非只读请求统一验证 CSRF Token。
- `AdminFormFeedbackMiddleware`：为后台 POST 成功跳转追加保存成功反馈参数。

后台 CSRF Token 默认从表单 `_token` 读取，也接受 `X-CSRF-Token` 请求头。Installer 不属于 `/admin`，继续使用自己的安装期 CSRF 边界。

Router 支持 Route 与 Route Group Middleware。路由级 Middleware 只在匹配成功后运行，并由各 Admin 路由组声明明确的 Permission Key；Middleware 不硬编码具体 URL。

完整分组及执行顺序参见 `docs/routing/route-groups.md`。

## 异常处理

`ExceptionHandler` 统一处理穿过 Pipeline 的异常：

- 已知 `HttpException` 使用异常定义的 HTTP 状态码和安全消息。
- 未知异常只记录异常类型、请求方法和路径，不记录异常消息、Trace、请求 Body 或 Header，避免密码、数据库 DSN、API Secret、Session/CSRF Token 或第三方凭证随异常对象进入日志。
- 生产环境只返回 `Internal Server Error`，不暴露异常消息。
- Debug 环境可返回异常消息，便于本地诊断。
- 请求的 `Accept` 包含 `application/json` 时返回统一 JSON 错误对象；Validation 异常额外包含 `errors` 字段。

当前标准异常状态码：

- `AuthenticationException`：401。
- `AuthorizationException`：403。
- `CsrfTokenMismatchException`：419。
- `ValidationException`：422，并可携带字段错误集合。
- `NotFoundException`：404。

Controller 可以为预期表单错误直接返回 Response；未知异常统一交给 Kernel 记录并转换为安全的 500 响应。

## Response Factory

`Jinstan\Core\Http\ResponseFactory` 由 Foundation Provider 注册，可创建：

- `text()`：普通文本或自定义响应。
- `html()`：带 UTF-8 Content-Type 的 HTML。
- `json()`：使用异常安全编码的 JSON。
- `redirect()`：默认 303 跳转。
- `file()`：读取可访问文件并设置 Content-Type 与 Content-Length。

Core 与官方 Plugin 均通过 Response Factory 创建 HTTP Response，不暴露具体 PSR-7 实现。
