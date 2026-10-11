<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Database\Queries;

use Jinstan\Core\Application\Database\BackupGateway;
use Jinstan\Core\Application\Database\DatabaseOperations;

final class DatabaseToolsQuery
{
    public function __construct(
        private readonly DatabaseOperations $database,
        private readonly BackupGateway $backups,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function execute(array $parameters): array
    {
        $driver = $this->database->driver();
        $backupPagination = $this->backups->paginate($this->page($parameters['backup_page'] ?? 1));

        return [
            'driver' => $driver,
            'migrations' => $this->database->migrationCount(),
            'integrity' => $this->integrity(),
            'sqlite' => $driver === 'sqlite',
            'backups' => $backupPagination['items'],
            'backupPage' => $backupPagination['page'],
            'backupPages' => $backupPagination['pages'],
            'backupTotal' => $backupPagination['total'],
        ];
    }

    /** @return array{value: string|null, translation: string|null} */
    public function integrity(): array
    {
        return $this->database->integrity();
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $page === false ? 1 : (int) $page;
    }
}
