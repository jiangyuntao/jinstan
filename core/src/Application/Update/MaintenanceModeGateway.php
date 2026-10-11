<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Update;

interface MaintenanceModeGateway
{
    public function enabled(): bool;

    public function enable(): void;

    public function disable(): void;
}
