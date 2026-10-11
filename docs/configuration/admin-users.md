# 后台用户管理

`UserRequest` 负责用户和个人资料的输入规范化与验证。普通用户创建密码至少 6 个字符；编辑时允许留空以保持原密码。Installer 的初始管理员继续使用更严格的至少 8 个字符规则。

`UserWriter` 负责用户写入用例，包括角色白名单检查、创建或更新用户、删除用户及当前账户资料更新。它禁止当前管理员从自己身上移除 `administrator` 角色；`UserRepository` 继续负责初始管理员不可删除、至少保留一名管理员、密码哈希和角色关系事务。

`UserAdminQuery` 负责用户清单、编辑目标和角色选项读取，不存在的用户通过明确异常交给 Controller 转换为 404。`UserAdminPresenter` 负责列表列定义、初始管理员不可删除标记、用户表单和个人资料 View Model。

Controller 只协调 `UserRequest`、Query、Presenter、`UserWriter`、表单错误响应和命名路由重定向。

## 角色管理

`RoleRequest` 负责角色标识、名称和权限数组的输入验证。`RoleWriter` 将已验证数据交给 `RoleRepository`，后者继续保证角色标识创建后不可修改、权限必须属于公共白名单、内置 `administrator` 自动持有全部权限且不可删除。

`RoleAdminQuery` 负责角色清单、编辑目标和权限选项读取；不存在的角色转换为 404。`RoleAdminPresenter` 负责角色清单与表单 View Model。`RoleAdminController` 仅协调上述边界、422 表单响应及命名路由重定向。
