# 发布包构建与验证

发布构建在项目根目录执行：

```bash
php build.php
```

构建流程会通过 `npm ci` 重建版本化前端资源，并在 `storage/updates/build-project-*` 中复制最小源码结构后安装 Composer 生产依赖。这个目录模拟发布包最终的 `core/vendor`、`core/src` 与 `site` 相对位置，确保优化后的 Composer Autoload 在 ZIP 解压后仍指向正确源码。打包只读取该生产依赖目录，不会删除或替换工作区中的 PHPUnit、PHP CS Fixer 等开发依赖；隔离目录在成功、异常或进程结束时都会清理。

生成的 `dist/jinstan-<version>.zip` 包含可通过 `/install` 启动的完整新站点结构，并同时生成 `.zip.sha256` 校验文件。压缩包包含 Core、生产 Vendor、Config、Site、官方 Plugin、Theme、文档、许可证、Public 入口与空 Storage 目录，不包含测试、Node 依赖、Git 数据、开发工具或现有站点运行数据。

构建后必须执行：

```bash
php scripts/verify-release.php dist/jinstan-<version>.zip
```

验证器会检查：

- 压缩包只有一个安全的版本根目录；
- README、License、安全策略、Installer、自动加载、默认 Theme 和 Storage 目录完整；
- PHPUnit、PHP CS Fixer、测试目录、Node 依赖和运行数据未进入包；
- `core/assets/manifest.php` 中的所有前端资源哈希一致；
- 解压到全新临时目录后，通过真实 HTTP Bootstrap 完成 SQLite Web 安装；
- 使用新建管理员完成登录并访问后台概览；
- 在安装前后分别执行 CLI 资源清单与数据库状态命令。

发布包验证不替代 Stable Upgrade Matrix。已有站点升级仍必须按 [`core-update.md`](core-update.md) 验证备份、维护模式、Migration、Health Check 和失败回滚。
