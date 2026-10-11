<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\System\Queries;

use Jinstan\Core\Application\System\RuntimeStorageGateway;

final class CacheLogsQuery
{
    public function __construct(private readonly RuntimeStorageGateway $storage)
    {
    }

    /** @return list<array{name: string, path: string, size: int}> */
    public function caches(): array
    {
        return $this->storage->caches();
    }

    /** @return array{log: string, size: int} */
    public function logs(): array
    {
        return $this->storage->logs();
    }
}
