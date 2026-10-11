<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Database\Plugin\PluginMigrationRunner;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class PluginMigrationRunnerTest extends TestCase
{
    public function testItRunsPluginMigrationsOnceWithAnIsolatedVersion(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        $runner = new PluginMigrationRunner($connection, new MigrationRunner());
        $pluginPath = dirname(__DIR__) . '/Fixtures/PluginWithMigrations';

        $runner->migrate('test-plugin', $pluginPath);
        $runner->migrate('test-plugin', $pluginPath);

        self::assertTrue($connection->tablesExist(['test_plugin_records']));
        self::assertSame(
            1,
            (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM migration_versions WHERE version = ?',
                ['plugin:test-plugin:Version202609270001'],
            ),
        );
    }

    public function testItRejectsAnInvalidPluginHandle(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        $runner = new PluginMigrationRunner($connection, new MigrationRunner());

        $this->expectException(\InvalidArgumentException::class);
        $runner->migrate('../unsafe', dirname(__DIR__) . '/Fixtures/PluginWithMigrations');
    }

    public function testItIgnoresAPluginWithoutMigrations(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        $runner = new PluginMigrationRunner($connection, new MigrationRunner());

        $runner->migrate('empty-plugin', dirname(__DIR__) . '/Fixtures');

        self::assertFalse($connection->tablesExist(['migration_versions']));
    }
}
