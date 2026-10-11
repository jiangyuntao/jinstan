<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Update;

use Jinstan\Core\Application\Update\MaintenanceModeGateway;

final class MaintenanceMode implements MaintenanceModeGateway
{
    public function __construct(private readonly string $path)
    {
    }
    public function enabled(): bool
    {
        return is_file($this->path);
    }
    public function enable(): void
    {
        file_put_contents($this->path, 'maintenance', LOCK_EX);
    }
    public function disable(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
