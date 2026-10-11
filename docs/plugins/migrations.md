# Plugin Migration

Plugin 可在根目录的 `migrations/` 中放置数据库迁移：

```text
plugins/ExamplePlugin/
├── plugin.json
├── Plugin.php
└── migrations/
    └── Version202609270001.php
```

迁移类继承公开的 `Jinstan\Core\Database\Migration`，在 `up()` 中使用 `Schema` 定义跨数据库结构：

```php
final class Version202609270001 extends Migration
{
    public function getDescription(): string
    {
        return '创建示例记录表。';
    }

    public function up(Schema $schema): void
    {
        $records = $schema->createTable('example_records');
        $records->addColumn('id', 'integer', ['autoincrement' => true]);
        $records->setPrimaryKey(['id']);
    }
}
```

CMS 会在首次安装、重新安装以及版本升级时，先执行尚未记录的 Plugin Migration，再调用对应生命周期方法。每个版本只执行一次，记录使用 `plugin:<plugin-name>:<version>` 前缀，避免与 Core 或其他 Plugin 的迁移版本冲突。

Plugin 不会获得数据库 `Connection`。结构变更必须通过 Migration 完成，不得在 `register()`、`boot()` 或普通请求中隐式建表。表名应带有可识别的 Plugin 前缀，并避免使用仅 SQLite、MySQL 或 PostgreSQL 支持的专属类型和语法。
