# Settings 与 Navigation Repository Contracts

## Settings

- `SettingsReadRepository`：读取单个设置和 `custom.*` 设置集合。
- `SettingsWriteRepository`：设置、删除、替换自定义设置和事务化保存系统设置。
- `SettingsRepositoryContract`：需要同时读写时使用的组合契约。

系统设置 Presenter 只依赖读取接口，保存用例依赖组合接口。`Infrastructure\\Persistence\\Settings\\SettingsRepository` 是当前数据库适配实现，Provider 将具体类及三个接口指向同一实例。

## Navigation

- `NavigationReadRepository`：菜单、菜单项树、单项及翻译读取。
- `NavigationWriteRepository`：创建菜单、菜单项 CRUD 和完整集合排序。
- `NavigationRepositoryContract`：导航读写组合契约。

数据库实现位于 `Infrastructure\\Persistence\\Navigation\\NavigationRepository`。

导航页面 Query、翻译解析器和前台 Navigation Component 只依赖读取接口；NavigationWriter 只依赖写入接口。层级循环、父级归属、目标白名单和完整集合排序校验继续由数据库适配实现负责。

这些契约均不暴露 Connection 或 SQL。Theme 和 Plugin 可依赖读取接口展示数据，但不应绕过后台 Permission 直接使用写入接口处理用户请求。
