<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\System\Services;

use Jinstan\Core\Application\System\RuntimeStorageGateway;

final class CacheLogsMaintenance
{
    public function __construct(private readonly RuntimeStorageGateway $storage)
    {
    }

    public function clearCache(): void
    {
        $this->storage->clearCache();
    }

    public function clearLogs(): void
    {
        $this->storage->clearLogs();
    }
}
