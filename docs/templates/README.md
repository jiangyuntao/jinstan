# Template 指南

Template 决定一条 Content 或一个 Content 列表如何显示，文件必须使用 `.jst` 扩展名。

解析顺序为：Content 指定 Template、Content Type 默认 Template、Theme Fallback。模板只接收准备好的展示数据，不访问 Container、数据库或执行写操作。

详情模板通常位于 `themes/<theme>/templates/<name>.jst`。新增模板后还需在 `theme.json` 中声明名称、标签和用途。
