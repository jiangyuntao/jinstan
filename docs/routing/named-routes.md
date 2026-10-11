# 命名路由与 URL Generator

## 注册命名路由

`Router::get()` 和 `Router::post()` 返回 Route 对象。原有调用无需修改；需要生成 URL 的稳定路由可以追加名称：

```php
$router->get('/login', [$login, 'show'])->name('login.show');
$router->post('/login', [$login, 'login'])->name('login.store');
```

路由名必须非空并在当前 Router 中唯一。对同一个 Route 再次调用 `name()` 会替换旧名称。

## 生成 URL

```php
$url = $urls->route('content.show', [
    'locale' => 'zh-CN',
    'slug' => 'hello world',
    'preview' => 1,
]);
```

对于路径 `/{locale}/content/{slug}`，结果为：

```text
/zh-CN/content/hello%20world?preview=1
```

路径参数会使用 `rawurlencode()`；未被路径消费的参数进入 Query String。缺少必填参数或使用未知路由名时抛出 `InvalidArgumentException`。

## 首批 Core 路由名

- `core.health`
- `home`
- `install.show`
- `install.store`
- `login.show`
- `login.store`
- `admin.dashboard`
- `admin.content-types.index`
- `admin.plugins.index`
- `admin.themes.index`
- `admin.database.index`
- `admin.users.index`
- `admin.roles.index`
- `admin.profile`
- `admin.settings.system`
- `admin.languages.index`
- `admin.cache.index`
- `admin.logs.index`
- `logout`
- `sitemap`

上述名称覆盖当前稳定入口及常用跳转目标。动态 Content、带资源参数的 Admin CRUD 和 Plugin 路由会按实际调用需求渐进命名，避免产生没有消费者或语义不稳定的名称。

`core.health` 对应独立的 `GET /health`，在维护模式中仍可用；站点首页 `/` 不在维护白名单。

## Latte View 中生成 URL

Admin View 可以使用全局 `route()` Helper：

```latte
<a href="{route('admin.dashboard')}">Dashboard</a>
<form method="post" action="{route('logout')}">
```

参数数组与 PHP UrlGenerator 语义一致。路径占位参数会进入 URL，其余参数生成 Query String：

```latte
<a href="{route('admin.settings.system', ['tab' => 'predefined'])}">Settings</a>
```

View 只负责调用稳定路由名，不访问 Router、Container 或路由集合。新增后台固定链接时应优先使用该 Helper；动态 Content URL 在对应路由完成命名前继续使用现有展示数据。

后台 CRUD 路由采用一致命名：索引使用 `.index`，新建页使用 `.create`，创建提交使用 `.store`，编辑页使用 `.edit`，更新提交使用 `.update`，删除使用 `.delete`。例如：

```text
admin.users.index
admin.users.create
admin.users.store
admin.users.edit
admin.users.update
admin.users.delete
```

带资源标识的路由通过参数数组生成，UrlGenerator 负责 URL 编码。表单 HTTP Method 仍以实际 Router 支持的方法为准，不因命名约定改变现有 POST 行为。

当前已完成命名路由迁移的后台 View 分组：

- Login。
- User 与 Role。
- Language。
- Plugin。
- Database。
- Profile。
- Cache 与 Logs。
- Content 与 Content Type。
- Taxonomy 与 Navigation。
- Theme 工作区。

Theme 工作区的主题选择、文件选择、保存、创建、重命名与删除操作均通过命名路由生成；查询参数由 UrlGenerator 编码。Controller 与表单服务生成的动态 URL 按业务分组继续迁移。

Content 后台的列表操作、表单提交与删除、保存后跳转、父内容搜索、Taxonomy 搜索和分页链接也已迁移到 UrlGenerator。`AdminContentForm` 在生产装配中接收同一 UrlGenerator，不再自行决定路由结构。

Taxonomy 的词汇表列表、分页及表单返回地址，以及 Navigation 写操作完成后的菜单与 Locale 返回地址，均由对应 Controller 使用 `admin.taxonomies.index` 和 `admin.navigation.index` 生成。

Theme Controller 的主题与文件操作返回地址、Plugin Controller 的列表返回与设置保存反馈地址也已迁移。`theme`、`file`、Plugin 名称和 `_feedback` 不再由 Controller 手工拼接或编码。

User、Role、Language Controller 的保存、删除、资料更新和退出登录重定向已迁移；Settings Controller 的活动 Tab 返回地址及系统设置表单 Action 也使用命名路由。

Database Tools、Content Type 与 Cache/Logs Controller 的跳转均已接入 UrlGenerator。

Dashboard 的 Content Type 卡片、Login Controller 的成功跳转、Admin Security Middleware 的未登录跳转和 Installer 表单 Action 已完成迁移。后台附件预览路由命名为 `admin.media.show`，AdminContentForm 通过存储路径参数生成预览 URL。Installer 完成后的 `/` 保持为协议目标，因为当前安装请求尚未注册已安装站点的首页路由。

生产请求认证链开始将 UrlGenerator 收紧为必需依赖：Login Controller 与 Admin Security Middleware 已移除字符串路径回退。测试通过 `TestUrlGenerator` 注册实际命名路由，不再依赖生产类的隐式默认地址。

Installer 与 AdminContentForm 也已将 UrlGenerator 收紧为必需依赖。安装表单、Content 创建/更新/删除、父级搜索和后台附件预览均要求对应命名路由存在；无效的附件存储路径会被明确拒绝，不再拼接为 URL。

Content Admin Controller 与 Dashboard 已将 UrlGenerator 收紧为必需依赖。Controller 的列表、分页、编辑、删除与 Taxonomy 搜索 URL 不再包含路径回退；Dashboard 统一从 Content Type Registry 构建卡片，不再维护另一份内置类型 URL 清单。

Taxonomy、Navigation、Theme 与 Plugin Admin Controller 已将 UrlGenerator 收紧为必需依赖，删除列表返回、分页、主题文件上下文和插件设置反馈的路径回退。Theme Controller 同时要求 SettingsRepository，用于可靠保护当前活动主题。

User、Role、Language、Settings、Database Tools 与 Cache/Logs Controller 已将 UrlGenerator 收紧为必需依赖，保存、删除、退出及工具操作不再存在固定路径回退。

Content Type Controller 已完成必需依赖收紧。未注册且功能已由 Dashboard 与 Database Tools 覆盖的 System Info Controller 已移除；Reading Settings 的功能已合并至系统设置的“预定义页面”Tab，旧 Controller、View 与重定向路由均已移除。

AdminViewRenderer 最后完成必需依赖收紧。Latte `route()` 遇到未知路由或缺少参数时会直接抛出异常，使错误在测试和开发阶段暴露；测试 Renderer 使用完整命名路由表，不再返回 `#` 占位地址。
