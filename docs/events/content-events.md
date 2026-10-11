# Content Events

Core 使用对象事件，不使用字符串 Hook。Plugin 通过 `PluginRegistrar::events()` 注册 Listener：

```php
$registrar->events()->listen(
    ContentPublished::class,
    static function (ContentPublished $event): void {
        // $event->content 是已更新为 published 状态的 Content。
    },
);
```

当前公开事件：

- `ContentCreated`：携带新建的 `Content` 和已规范化的 `ContentTranslation`。
- `ContentUpdated`：携带更新后的 `Content` 和已保存的 `ContentTranslation`。
- `ContentPublished`：内容从非发布状态切换到 `published` 后触发。
- `ContentUnpublished`：内容从发布状态切换到 `draft` 后触发。
- `ContentDeleted`：数据库删除提交且无引用附件清理完成后触发。

事件同步执行，并保持 Listener 注册顺序。Listener 抛出的异常不会被吞掉；如果事件位于上层数据库事务中，异常会使该事务回滚。因此 Listener 应保持短小、明确，不应修改与事件无关的全局状态，也不应在可能回滚的 Listener 中执行不可逆的远程操作。

`events()` 返回只允许 `listen()` 的 `EventListenerRegistrar`。Plugin 不能通过公共 Registrar 主动分发或伪造 Core 事件。事件类或接口必须真实存在，注册未知类名会被拒绝。
