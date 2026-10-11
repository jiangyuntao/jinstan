<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Database\Plugin;

use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Plugin\PluginMigrationGateway;

final readonly class PluginMigrationRunner implements PluginMigrationGateway
{
    public function __construct(
        private Connection $connection,
        private MigrationRunner $migrations,
    ) {
    }

    public function migrate(string $plugin, string $pluginPath): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $plugin) !== 1) {
            throw new \InvalidArgumentException(sprintf('无效的 Plugin 标识：%s', $plugin));
        }

        $migrationPath = $pluginPath . '/migrations';
        if (!is_dir($migrationPath)) {
            return;
        }

        $this->migrations->migrate($this->connection, $migrationPath, 'plugin:' . $plugin . ':');
    }
}
