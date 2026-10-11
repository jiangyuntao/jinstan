<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Installer;

interface InstallationLockGateway
{
    public function exists(): bool;

    public function create(): void;
}
