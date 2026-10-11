<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Database;

use Jinstan\Core\Application\Update\CoreMigrationGateway;

final class CoreMigrationRunner implements CoreMigrationGateway
{
    public function __construct(
        private readonly MigrationRunner $migrations,
        private readonly Connection $connection,
    ) {
    }

    public function migrate(string $migrationPath): void
    {
        $this->migrations->migrate($this->connection, $migrationPath);
    }
}
