# 1.0 技术边界与后续工作

## 已完成的结构调整

- Bootstrap 只负责创建 Foundation Application 和按顺序注册 Provider。
- HTTP Kernel、Middleware、Router、异常处理和 Response Factory 已形成明确边界。
- Admin Controller 通过构造器接收 Query、Presenter 与写入 Service，不直接访问 Container。
- Content、Settings、Auth、Taxonomy、Navigation、Plugin 等持久化实现均位于 Infrastructure。
- Plugin 只使用文档列出的 Public API，不再保留旧 Namespace、`PluginContext` 或 `class_alias`。
- 所有 Core 与 Theme 模板统一使用 `.jst`。

## 1.0 保留的限制

- Core 更新能力提供校验、维护模式、替换、Migration、健康检查与回滚服务；自动下载和远程更新源不属于 1.0。
- SQLite 提供内置文件备份；MySQL/MariaDB/PostgreSQL 应继续使用数据库官方备份工具。
- 数据库工具提供状态、完整性、优化、无效 Redirect 清理与备份管理。跨数据库迁移采用备份、空库安装和受控导入流程，不在普通请求中自动切换连接。
- Content Markdown 后台使用原生可访问文本编辑器，避免依赖停止维护且包含已知漏洞的浏览器编辑器。
- Content Type 定义仍由站点配置保存；1.0 不引入可视化低代码 Builder。

## 后续版本候选项

- 完整 CSP Nonce 与 Theme/Plugin 资源声明机制。
- 可恢复的数据库跨驱动导入向导。
- Core 更新包后台上传界面与签名信任链。
- 更细粒度的资源 Policy 和审计日志。

这些项目不改变 1.0 的 Public API 与升级边界；实现时必须补充 Migration、回滚、跨数据库测试和文档。
