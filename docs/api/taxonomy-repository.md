# Taxonomy Repository Contracts

Taxonomy 持久化能力按读取和写入拆分：

- `TaxonomyReadRepository`：Taxonomy、Term、翻译、分页、搜索、已选关系和选择校验。
- `TaxonomyWriteRepository`：Taxonomy／Term CRUD、排序、Content 分类关系同步和事务。
- `TaxonomyRepositoryContract`：同时需要读取与写入时使用的组合契约。

`Infrastructure\\Persistence\\Taxonomy\\TaxonomyRepository` 是当前数据库适配实现，Provider 将新旧具体类 ID 和三个接口绑定到同一实例。Content 表单、搜索和 Taxonomy 页面 Query 只依赖读取契约；表单提交、排序和 ContentService 使用组合契约。

契约不暴露 Connection、SQL 或数据库驱动类型。Term Translation 仍通过 Taxonomy 读取契约提供，与 Content Translation 一样从第一版保留多语言边界。
