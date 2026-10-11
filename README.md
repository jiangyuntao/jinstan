# Jinstan CMS

Jinstan 是面向 SEO 内容站点的轻量 CMS。Core 使用 PHP 8.3+，默认采用 SQLite，也支持 MySQL、MariaDB 与 PostgreSQL；后台采用服务端渲染、Tailwind CSS 和 Alpine.js，前台 Theme 使用 Latte `.jst` 模板。

## 安装

1. 将正式发布包解压到站点目录。
2. 将 Web 根目录指向 `public/`。
3. 确保 `storage/` 可写。
4. 浏览器访问 `/install`，按页面提示创建站点和管理员。

安装完成后 Installer 会自动锁定。管理员密码至少 6 位。生产部署步骤、扩展要求和数据库配置见 [安装文档](docs/installation/README.md)。

## 本地开发

```bash
composer install
npm ci
npm run build
php -S 127.0.0.1:8080 -t public
```

测试和质量检查：

```bash
composer test
composer lint
composer audit --locked
npm audit --omit=dev
```

构建正式包：

```bash
php build.php
php scripts/verify-release.php dist/jinstan-1.0.0.zip
```

## 目录边界

- `core/`：可替换升级的 CMS Core。
- `site/`：当前站点的少量定制逻辑。
- `plugins/`：行为与系统能力扩展。
- `themes/`：Layout、Template、Component View 与静态资源。
- `storage/`：数据库、缓存、日志、备份和更新临时数据。
- `docs/`：安装、扩展、升级和公共 API 文档。

不要通过修改 Core 实现站点定制。可复用能力应放入 Plugin，展示结构应放入 Theme。

## 许可证与安全

项目以 [MIT License](LICENSE) 发布。安全问题请按 [SECURITY.md](SECURITY.md) 中的方式私下报告。
