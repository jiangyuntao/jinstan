# PluginServiceProvider

## 职责

`Jinstan\Core\Providers\PluginServiceProvider` 负责 Plugin 基础设施装配和启动：

- `Infrastructure\\Persistence\\Plugin\\PluginStateRepository`（通过 Plugin 状态读写契约接入）
- `PluginRegistrar` 与只写的 `PluginServiceRegistrar`
- `PluginManager`
- `PluginAdminController`

未安装状态没有 Connection，因此不会注册或启动 Plugin。

## 生命周期

Plugin Provider 严格区分两个阶段：

```text
register()
→ 创建 Plugin 状态存储、Registrar、Manager 和 Admin Controller

boot()
→ 发现 plugins/ 下的 Manifest
→ 执行必要的 install / upgrade
→ 为启用的 Plugin 执行 register / boot
```

插件发现和路由、Component 注册不得发生在 Provider 的 `register()` 中，因为此时其他 Provider 可能尚未完成注册。

## Public Registrar

新 Plugin 通过 `PluginRegistrar` 获得受控的 Route、Component、Content Type、Service、只读 Content／Settings、CSRF 和 Response 能力。`PluginServiceRegistrar` 只允许注册 Plugin 自有对象及其真实实现的契约，禁止读取 Container 和覆盖已有 Service。

Plugin 入口必须实现 `PluginServiceProvider`，并通过 `PluginRegistrar` 注册能力。Core 不再向 Plugin 暴露 Router、ComponentRegistry、具体 Repository 或 Container。

## 路由顺序

Foundation Application 在所有 Provider 完成 `register()` 后执行 Plugin `boot()`，确保：

- Core 静态 Admin 路由先注册。
- Plugin 路由在 Core 静态入口与动态 Content Route 之间注册。
- 动态 Content Type 前台路由保持原顺序。
- 完整路由快照保持稳定。

## 安全边界

Plugin Provider 不替 Plugin 选择权限或绕过 CSRF。ExternalOrderPlugin 的前台写路由显式验证 CSRF；Plugin 后台路由必须声明 Authentication、CSRF 和 Permission Middleware。

Plugin 不得修改 Core、vendor 或 Theme 原文件，也不得在普通 boot 中隐式修改 Schema。

## 测试

`tests/Integration/PluginServiceProviderTest.php` 验证：

- 未安装状态不注册 Plugin 服务。
- 官方 Plugin 可以被发现并保持启用状态。
- PluginRegistrar、PluginServiceRegistrar、PluginManager 和 PluginAdminController 正确注册。
- HelloPlugin 的 Service 与 Controller 经受控注册器进入共享 Router，且路由正常响应。
- 完整路由顺序保持基线一致。
