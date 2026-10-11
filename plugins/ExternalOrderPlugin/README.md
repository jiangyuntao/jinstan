# External Order Plugin

官方扩展示例：把 Product、表单 Component、Plugin Route、校验、Service 与第三方 HTTP Gateway 串联起来，而不修改 Core。

## 安装与启用

将目录放在 `plugins/ExternalOrderPlugin`，进入后台“插件”页面安装并启用 `external-order`。在 Product 模板中渲染 `external-order-form` Component。

## 配置

- `submit_url`：第三方订单 JSON API 地址。留空时使用不会联网的 Fake Gateway。
- `parameters`：目标字段名到 `product_id`、`email` 数据源的 JSON 映射。

Plugin 只在用户明确配置 URL 后向该地址发送产品 ID 和邮箱。请确保目标服务使用 HTTPS，并在站点隐私政策中说明数据接收方。

真实 HTTP Gateway 要求 PHP cURL 扩展。Gateway 只允许 HTTP/HTTPS 的 80、443 端口，拒绝本机、私网、链路本地和保留地址；DNS 解析结果固定到本次 cURL 请求，禁止重定向，启用 TLS 证书校验，并把响应体限制为 1 MB。

## 路由与数据

`POST /external-order` 校验 CSRF、产品、邮箱和请求数据后提交订单。同一来源 IP 每分钟最多提交 10 次，超限返回 `429` 与 `Retry-After`。Plugin 不创建数据表；配置存入 Core Plugin Settings。

## 升级与卸载

1. 停用 Plugin。
2. 备份站点并替换整个 Plugin 目录。
3. 在后台重新启用，生命周期会执行版本升级。

卸载会移除 Plugin 状态与配置；本 Plugin 没有本地业务表或本地订单数据。第三方系统中已经创建的订单不会被删除。
