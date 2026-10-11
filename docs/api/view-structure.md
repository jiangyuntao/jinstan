# Core View 结构

Core 的 Latte View 统一存放在 `core/resources/views/`，PHP 源码目录不存放模板。

```text
core/resources/views/
├── admin/
│   ├── layout.jst
│   ├── *.jst
│   ├── fields/
│   ├── lists/
│   └── partials/
├── components/
└── installer/
```

- `admin/layout.jst`：后台公共 Layout。
- `admin/*.jst`：后台 Page View。
- `admin/fields/`：Content Field Type 的后台控件。
- `admin/lists/`：列表单元格与表格 Partial。
- `admin/partials/`：通用表单操作区、危险操作确认等 Partial。
- `components/`：Core Component 默认 View，可由 Theme 覆盖。
- `installer/`：`/install` 安装流程 View。

所有模板必须使用 `.jst`。View 只接收 Presenter 准备的数据，不访问 Container、数据库或文件写入 API。需要输出 JSON Script Payload 时，生产端必须使用 `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`；新增 `noescape` 会触发架构测试，必须经过显式安全审查。

Plugin View 使用独立命名空间和插件自己的 `views/` 目录，Theme 继续使用 `layouts/`、`templates/`、`partials/` 与 `components/`，不复制 Core View 目录结构。
