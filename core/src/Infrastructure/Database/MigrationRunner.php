<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Database;

use Jinstan\Core\Database\Migration;
use Jinstan\Core\Database\Schema\Schema;

final class MigrationRunner
{
    public function migrate(Connection $connection, string $migrationPath, string $versionPrefix = ''): void
    {
        $this->ensureVersionTable($connection);

        foreach ($this->discover($migrationPath) as $version => $migration) {
            $version = $versionPrefix . $version;
            if ($this->executed($connection, $version, $migration)) {
                continue;
            }

            $this->apply($connection, $version, $migration);
        }

        $this->ensureCorePermissions($connection);
    }

    private function executed(Connection $connection, string $version, Migration $migration): bool
    {
        return $connection->fetchOne(
            'SELECT version FROM migration_versions WHERE version = ? OR version = ?',
            [$version, $migration::class],
        ) !== false;
    }

    private function ensureVersionTable(Connection $connection): void
    {
        if ($connection->tablesExist(['migration_versions'])) {
            return;
        }

        $dateTimeType = $connection->driver() === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $connection->executeStatement(sprintf(
            'CREATE TABLE migration_versions (version VARCHAR(191) NOT NULL PRIMARY KEY, executed_at %s NOT NULL)',
            $dateTimeType,
        ));
    }

    private function apply(Connection $connection, string $version, Migration $migration): void
    {
        $schema = new Schema();
        $migration->up($schema);
        $sql = $schema->statements($connection->driver());
        $transactional = $connection->driver() !== 'mysql';

        if ($transactional) {
            $connection->beginTransaction();
        }
        try {
            foreach ($sql as $statement) {
                $connection->executeStatement($statement);
            }
            $migration->upData($connection);
            $connection->insert('migration_versions', [
                'version' => $version,
                'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            if ($transactional && $connection->isTransactionActive()) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactional && $connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, Migration> */
    private function discover(string $migrationPath): array
    {
        $migrations = [];
        foreach (glob(rtrim($migrationPath, '/') . '/Version*.php') ?: [] as $file) {
            require_once $file;
            foreach (get_declared_classes() as $class) {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isInstantiable() && is_a($class, Migration::class, true) && $reflection->getFileName() === realpath($file)) {
                    $migrations[pathinfo($file, PATHINFO_FILENAME)] = new $class();
                }
            }
        }

        ksort($migrations, SORT_STRING);

        return $migrations;
    }

    private function ensureCorePermissions(Connection $connection): void
    {
        if (!$connection->tablesExist(['roles', 'permissions', 'role_permissions'])) {
            return;
        }

        if ($connection->fetchOne('SELECT role_key FROM roles WHERE role_key = ?', ['administrator']) === false) {
            $connection->insert('roles', ['role_key' => 'administrator', 'label' => '管理员']);
        }
        $permissions = [
            'content.manage' => '内容管理',
            'settings.manage' => '系统设置',
            'system.manage' => '系统工具',
            'users.manage' => '账户管理',
        ];
        foreach ($permissions as $key => $label) {
            if ($connection->fetchOne('SELECT permission_key FROM permissions WHERE permission_key = ?', [$key]) === false) {
                $connection->insert('permissions', ['permission_key' => $key, 'label' => $label]);
            }
            if ($connection->fetchOne('SELECT role_key FROM role_permissions WHERE role_key = ? AND permission_key = ?', ['administrator', $key]) === false) {
                $connection->insert('role_permissions', ['role_key' => 'administrator', 'permission_key' => $key]);
            }
        }
    }
}
