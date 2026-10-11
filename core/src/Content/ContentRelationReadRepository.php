<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

/** Public API：读取 Content Relation 的最小契约。 */
interface ContentRelationReadRepository
{
    /** @return list<Content> */
    public function related(int $sourceId, string $relation): array;
}
