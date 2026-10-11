# User、Role 与 Permission Repository Contracts

后台账户持久化按读取和写入拆分：

- `UserReadRepository`：用户列表与单用户读取。
- `UserWriteRepository`：创建用户、更新资料、分配角色和删除用户。
- `UserRepositoryContract`：需要用户读写能力时使用的组合接口。
- `RoleReadRepository`：角色、角色详情及可分配 Permission 查询。
- `RoleWriteRepository`：角色保存、Permission 分配和删除。
- `RoleRepositoryContract`：角色读写组合接口。

`Infrastructure\\Persistence\\Auth\\UserRepository` 和 `Infrastructure\\Persistence\\Auth\\RoleRepository` 是当前数据库适配实现。Provider 注册当前具体类及读写契约；后台 Query 只依赖读取接口，Writer 仅获得完成用例所需的写入或组合能力。

密码哈希、最后一名管理员保护、初始管理员保护和事务仍由持久化实现负责。接口不返回数据库 Connection，也不暴露密码明文。

## Authentication

`AuthenticationReadRepository` 是 Authenticator 使用的 Internal API，仅提供：

- 按邮箱读取用户 ID 与密码哈希。
- 判断用户是否拥有内置 administrator 角色。
- 查询用户是否拥有指定 Permission。

`Infrastructure\\Persistence\\Auth\\DatabaseAuthenticationRepository` 是数据库适配实现。`Application\\Auth\\Services\\Authenticator` 只依赖 `AuthenticationReadRepository`，负责 `password_verify()`、Session ID 更新、当前用户 ID 和登出，不执行 SQL。凭据契约不会返回密码明文；调用方必须显式注入认证读取契约，数据库适配不会泄漏到 Application 类型。
