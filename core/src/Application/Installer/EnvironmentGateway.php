<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Installer;

interface EnvironmentGateway
{
    /** @return array<string, bool> */
    public function checks(): array;

    public function passes(): bool;
}
