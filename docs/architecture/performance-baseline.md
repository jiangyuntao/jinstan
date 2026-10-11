# Performance Baseline

使用可重复的本地基线观察 Core 启动与常用请求开销，不把特定开发机的毫秒数作为 CI 硬阈值。

运行：

```bash
php scripts/performance-baseline.php --iterations=50
```

脚本会创建隔离的临时 SQLite 站点，准备一个首页和一篇已发布文章，并以管理员身份采样：

- 完整 Application Bootstrap；
- 首页；
- Content 详情页；
- 后台 Content 列表。

输出为 JSON，包含平均值、P95、最大耗时和进程峰值内存。比较重构前后结果时应使用相同 PHP 版本、依赖锁文件、机器和迭代次数；首次结果可受 OPcache、文件系统缓存和 Latte 模板预热影响，因此建议连续执行三次并记录后两次。

该脚本只写入系统临时目录中以 `jinstan-baseline-` 开头的隔离目录，结束时自动清理，不读取或修改当前站点数据库。

## 1.0.0 参考结果

2026-10-04 在 PHP 8.3.31、SQLite、50 次请求采样下：首页平均 0.526 ms，Content 详情平均 0.578 ms，后台 Content 列表平均 0.220 ms，进程峰值内存 16 MiB。Bootstrap 包含冷启动，5 次平均 12.750 ms；这些数值只用于发现显著回退。
