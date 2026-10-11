# 多语言

后台界面语言与内容语言相互独立。站点设置包含默认 Locale、启用的 Locale 和回退 Locale；可翻译 Field、slug 与 SEO 字段按内容语言保存。

单语言站点只显示默认语言工作流。启用多个语言后，前台 Locale URL、canonical、hreflang 与 Sitemap 使用同一套语言配置，缺失翻译按照站点回退规则处理。

Plugin 的后台文案通过 `PluginRegistrar::translations()` 注册，不应把界面翻译存入内容翻译表。
