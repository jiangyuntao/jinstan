<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\System;

interface RuntimeStorageGateway
{
    /** @return list<array{name: string, path: string, size: int}> */
    public function caches(): array;

    /** @return array{log: string, size: int} */
    public function logs(): array;

    public function clearCache(): void;

    public function clearLogs(): void;
}
