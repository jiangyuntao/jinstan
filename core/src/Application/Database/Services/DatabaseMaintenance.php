<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Database\Services;

use Jinstan\Core\Application\Database\BackupGateway;
use Jinstan\Core\Application\Database\DatabaseOperations;

final class DatabaseMaintenance
{
    public function __construct(
        private readonly DatabaseOperations $database,
        private readonly BackupGateway $backups,
    ) {
    }

    public function optimize(): void
    {
        $this->database->optimize();
    }

    public function cleanup(): int
    {
        return $this->database->cleanup();
    }

    public function createBackup(): string
    {
        return $this->backups->create();
    }

    public function deleteBackup(string $id): void
    {
        $this->backups->delete($id);
    }

}
