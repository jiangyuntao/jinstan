# Layout 指南

Layout 是页面整体骨架，位于 Theme 的 `layouts/` 目录并使用 `.jst` 扩展名。Layout 负责站点外壳、导航区域和内容插槽，不负责业务写操作或数据库访问。

Template 选择完成后由 Theme Renderer 将内容结果交给 Layout。Theme 可以提供多个 Layout，但应保留可靠的默认回退。
