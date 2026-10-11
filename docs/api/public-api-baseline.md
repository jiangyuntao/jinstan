# Public API 使用基线

## 目的

本文档记录 1.0 Public API 收紧过程及最终边界。历史具体实现只用于说明迁移结果，不属于 Public API。

## 当前扩展依赖

| Core 类型 | 当前使用者 | 当前用途 | 目标处理 |
| --- | --- | --- | --- |
| `Jinstan\Core\Plugin\PluginServiceProvider` | HelloPlugin、ExternalOrderPlugin | Plugin 生命周期与注册入口 | 正式 Public API |
| `Jinstan\Core\Plugin\PluginRegistrar` | HelloPlugin、ExternalOrderPlugin | 受控注册 Route、Component、Service 等能力 | 正式 Public API |
| `Jinstan\Core\Component\Component` | ExternalOrderFormComponent | 注册页面 Component | 保留并正式标注 Public API |
| `Jinstan\Core\Security\CsrfTokenProvider` | ExternalOrderFormComponent | 表单 Token 展示 | 正式 Public API Contract |
| `Jinstan\Core\Content\ContentReadRepository` | ExternalOrderService | 查找 Product | 正式只读 Public API Contract |
| `Jinstan\Core\Security\RateLimiter` | ExternalOrderPlugin Route | 限制公开提交频率 | 正式 Public API Contract |

## 已收紧的边界

Plugin 现在通过以下受控能力扩展 Core：

- Route Registrar。
- Component Registrar。
- Settings Contract。
- Content Query Contract。
- CSRF Token Provider Contract。
- Rate Limiter Contract。

`PluginContext` 和早期 `Plugin` 接口已删除，Plugin 不能访问 Router、Container 或具体 Repository。

### ContentRepository 泄漏具体持久化

ExternalOrderPlugin 已改为依赖 Public API `ContentReadRepository`，只获得按 ID 读取 Product 所需能力，不再依赖具体 `ContentRepository`。

### CsrfToken 泄漏实现

`CsrfTokenProvider` 已成为 Plugin Public API，只暴露生成和验证 Token，不公开具体 Session Key 或实现类。ExternalOrderPlugin 的 Component 与 Route 已迁移到该契约。写路由仍应在适合时优先使用统一 CSRF Middleware。

## 1.0 结果

受控 Plugin Framework 已完成。当前正式 Public API 与 Internal API 见 [`plugin-public-api.md`](plugin-public-api.md)。官方 HelloPlugin 与 ExternalOrderPlugin 均使用 `PluginServiceProvider`。

## Internal 基线

除正式 Public API 文档列出的类型外，当前 `core/src/` 类型默认视为 Internal。后续任何需要开放给 Plugin 的类型，都必须：

- 有明确 Contract。
- 不泄漏具体数据库、文件系统或模板实现。
- 有扩展测试。
- 有使用示例和升级说明。
