<?php

declare(strict_types=1);

namespace Jinstan\Core\Taxonomy;

/** Public API：同时需要 Taxonomy 读取与写入能力的组合契约。 */
interface TaxonomyRepositoryContract extends TaxonomyReadRepository, TaxonomyWriteRepository
{
}
