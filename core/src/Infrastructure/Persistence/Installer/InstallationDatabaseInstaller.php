<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Installer;

use Jinstan\Core\Application\Installer\InstallationDatabaseGateway;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;

final class InstallationDatabaseInstaller implements InstallationDatabaseGateway
{
    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly MigrationRunner $migrations,
        private readonly InstallationSeeder $seeder,
        private readonly string $migrationPath,
    ) {
    }

    public function install(array $database, string $siteName, string $email, string $password): void
    {
        $connection = $this->connections->create($database);
        // 连接探测必须发生在 Migration 之前，便于安装页面准确反馈配置错误。
        $connection->executeQuery('SELECT 1');
        $this->migrations->migrate($connection, $this->migrationPath);
        $this->seeder->seed($connection, $siteName, $email, $password);
    }
}
