<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

/** Public API：同时需要 Content 读取与写入能力的组合契约。 */
interface ContentRepositoryContract extends ContentReadRepository, ContentWriteRepository
{
}
