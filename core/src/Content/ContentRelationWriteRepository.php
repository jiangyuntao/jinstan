<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

/** Public API：创建 Content Relation 的最小契约。 */
interface ContentRelationWriteRepository
{
    public function relate(int $sourceId, string $relation, int $targetId, int $position = 0): void;
}
