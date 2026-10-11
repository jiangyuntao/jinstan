# Core 路由文件

## 目录与职责

Core 路由集中放在 `core/routes/`。当前已建立：

- `install.php`：未安装环境可用的健康检查与 Installer 路由。
- `auth.php`：已安装环境的登录页面与登录提交路由。
- `admin.php`：后台路由，迁移期间按 Plugin 注册时序划分 section。
- `web.php`：Frontend、Media、Sitemap 与动态 Content 路由。

后续会依次补充 Web 与 Admin 路由文件。路由文件只声明 HTTP 入口，不构造业务服务，也不执行数据库查询。

## 文件契约

路由文件必须开启 strict types，并返回一个注册 Closure。依赖由 RouteServiceProvider 显式传入：

```php
return static function (RouteRegistrar $routes, ResponseFactory $responses): void {
    $routes->get('/install', [InstallController::class, 'show'])
        ->name('install.show');
};
```

路由文件通过 `RouteRegistrar` 注册路由，不直接获得 Router、Container 或 Application。Controller 使用类名 Action，在实际匹配请求时由 Container 解析。

Route Registrar 的 `load()` 会先验证文件存在且扩展名为 `.php`，再验证返回值是否可调用。文件契约无效时应用启动立即失败，避免系统在缺少部分路由的状态下继续运行。Core 与 Plugin 使用同一加载规则。

## Installer 边界

`core/routes/install.php` 的注册发生在数据库连接检查之前。因此全新环境仍可访问：

- `GET /`
- `GET /install`
- `POST /install`

检测到已安装数据库后，系统才继续注册 Auth、Admin、Frontend 与 Plugin 路由。这个顺序属于安装安全边界，后续迁移不得改变。

## Authentication 边界

`core/routes/auth.php` 仅在系统检测到数据库连接后加载，目前注册：

- `GET /login`
- `POST /login`

两条路由继续使用 `login.show` 与 `login.store` 名称。`POST /logout` 已由 Admin 路由文件接管，并保持位于 User Profile 路由之后的既有注册顺序。

## Admin 分区

Plugin 路由在当前 Provider 生命周期中位于两段 Core Admin 路由之间。为避免迁移过程中改变路由优先级，`core/routes/admin.php` 暂时接收 section 参数：

```php
$register($routes, 'before_plugins');
```

`before_plugins` 包含：

- 后台 Media 访问。
- Dashboard。
- Content 与 Content Type。
- Taxonomy。
- Navigation。

`after_plugins` 包含 Plugin 管理、Theme、Database、User、Role、Profile、Settings、Language、Cache、Logs 与 Logout。`after_content` 是明确的顺序边界，当前不注册额外兼容路由。

Admin 路由除 `/logout` 外统一注册在 `/admin` Prefix Group 下，并使用 Controller 类名 Action。RouteServiceProvider 在 Plugin boot 前后和动态 Content 路由后分别调用对应 section，从而保持完整路由优先级。未知 section 会立即抛出异常，避免静默漏注册；未来 Plugin 路由时序稳定后再收敛临时 section 参数。

## Web 分区

`core/routes/web.php` 按既有路由优先级分为：

- `before_auth`：首页、Locale 首页与 Sitemap。
- `after_auth`：公开 Media 文件。
- `content`：由 Content Type 定义生成的列表、详情及 Locale 路由。

动态 Content 路由继续遵守“静态列表优先、具体详情其次、通用 Page 最后”的顺序。`ContentRoutes` 只接收 Route Registrar，并通过 `has()` 避免覆盖已经注册的静态路由，不再依赖 Router 的 dispatch 或内部查询能力。

## View URL

AdminViewRenderer 将 UrlGenerator 封装为 Latte `route()` Helper。后台 Layout 和页面模板通过命名路由生成链接与表单 Action，不直接访问 Router 或 Container。路由重命名时只需修改路由声明，不需要在模板中批量替换字符串路径。
