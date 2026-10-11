<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Update;

interface CoreMigrationGateway
{
    public function migrate(string $migrationPath): void;
}
