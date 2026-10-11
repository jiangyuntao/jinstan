# 数据库测试矩阵

日常完整测试默认使用 SQLite：

```bash
core/vendor/bin/phpunit
```

关键迁移、Content 分页/多语言查询以及 Taxonomy 关系行为同时在 MySQL、MariaDB 和 PostgreSQL 上验证。GitHub Actions 的 `Tests` 工作流会分别启动 MySQL 8.4、MariaDB 11.4 和 PostgreSQL 17；MariaDB 与 MySQL 共用 PDO `mysql` Driver，但使用独立服务执行同一组断言。

本地可以通过以下变量接入独立测试数据库：

```text
JINSTAN_TEST_MYSQL_DSN=mysql:host=127.0.0.1;port=3306;dbname=jinstan_test
JINSTAN_TEST_MYSQL_USER=root
JINSTAN_TEST_MYSQL_PASSWORD=secret

JINSTAN_TEST_PGSQL_DSN=pgsql:host=127.0.0.1;port=5432;dbname=jinstan_test
JINSTAN_TEST_PGSQL_USER=postgres
JINSTAN_TEST_PGSQL_PASSWORD=secret
```

随后运行：

```bash
core/vendor/bin/phpunit \
  tests/Integration/ContentListingDatabaseTest.php \
  tests/Integration/TaxonomyDatabaseTest.php
```

未配置对应 DSN 时，该 Driver 的用例会明确标记为 skipped；SQLite 用例始终执行。测试使用随机 Content Type 与 Taxonomy 标识，并只清理由当前用例创建的数据，因此数据库必须是隔离的测试库，不能指向生产环境。
