<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Database;

use Jinstan\Core\Application\Database\DatabaseOperations;

final class DatabaseOperationsAdapter implements DatabaseOperations
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function driver(): string
    {
        return $this->connection->driver();
    }

    public function tableCount(): int
    {
        return count($this->connection->tableNames());
    }

    public function migrationCount(): int
    {
        return in_array('migration_versions', $this->connection->tableNames(), true)
            ? (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_versions')
            : 0;
    }

    public function integrity(): array
    {
        return match ($this->driver()) {
            'sqlite' => [
                'value' => (string) $this->connection->fetchOne('PRAGMA integrity_check'),
                'translation' => null,
            ],
            'mysql' => ['value' => null, 'translation' => 'database.mysql_integrity'],
            'pgsql' => ['value' => null, 'translation' => 'database.pgsql_integrity'],
        };
    }

    public function optimize(): void
    {
        match ($this->driver()) {
            'sqlite' => $this->connection->executeStatement('VACUUM'),
            'mysql' => $this->optimizeMySql(),
            'pgsql' => $this->connection->executeStatement('VACUUM (ANALYZE)'),
        };
    }

    public function cleanup(): int
    {
        if (!in_array('redirects', $this->connection->tableNames(), true)) {
            return 0;
        }

        // 指向自身的 Redirect 永远不能产生有效跳转，只会造成循环响应。
        return $this->connection->executeStatement(
            'DELETE FROM redirects WHERE source_path = target_path',
        );
    }

    private function optimizeMySql(): void
    {
        $tables = array_filter(
            $this->connection->tableNames(),
            static fn (string $table): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) === 1,
        );
        if ($tables !== []) {
            $this->connection->executeStatement('OPTIMIZE TABLE ' . implode(', ', $tables));
        }
    }
}
