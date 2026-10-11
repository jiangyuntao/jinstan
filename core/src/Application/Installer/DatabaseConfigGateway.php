<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Installer;

interface DatabaseConfigGateway
{
    /** @param array<string, mixed> $config */
    public function write(array $config): void;
}
