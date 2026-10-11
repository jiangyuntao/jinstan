# Hello Plugin

最小官方 Plugin 示例，展示 Service 注册、Controller 注入和独立 Route 文件。

## 安装与配置

将目录放在 `plugins/HelloPlugin`，在后台“插件”页面安装并启用 `hello-plugin`。本 Plugin 没有配置项。

启用后访问 `GET /hello-plugin`。路由由 `routes/web.php` 注册，Controller 通过 `PluginServiceRegistrar` 获取 `GreetingService`。

## 数据、升级与卸载

本 Plugin 不创建数据表，也不写入站点内容。升级时替换完整目录并通过后台重新启用。卸载只移除 Plugin 状态，不修改 Core、Theme 或用户内容。
