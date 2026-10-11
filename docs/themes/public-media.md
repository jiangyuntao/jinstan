# 公开媒体读取

公开媒体路由只允许读取由 Content Attachment Storage 生成的哈希路径：

```text
aa/bb/32位十六进制哈希.jpg
```

支持的扩展名与上传白名单一致：JPEG、PNG、WebP 和 PDF。

`Infrastructure\\Filesystem\\Content\\PublicMediaFileReader` 负责组合路由片段、校验路径，并通过 `PublicMediaAccessPolicy` 确认附件至少被一条已发布 Content 的 Media Field 引用后再读取文件。草稿、取消发布或已经没有发布引用的附件统一返回 404。`PublicMediaController` 不接触文件系统，只返回文件响应或 404。

成功响应包含：

- 实际 MIME 类型。
- `Cache-Control: public, max-age=300`，让取消发布后的公开缓存可以在短时间内失效。
- `X-Content-Type-Options: nosniff`。

路径格式错误、目录穿越尝试、文件不存在或读取失败均返回 404，且不会暴露真实存储路径。
