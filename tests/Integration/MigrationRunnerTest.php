<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    public function testItAppliesEachMigrationOnlyOnce(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-migrations-' . bin2hex(random_bytes(6)) . '.sqlite';
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $path]);
        $runner = new MigrationRunner();
        $migrations = dirname(__DIR__, 2) . '/core/src/Database/Migrations';

        $runner->migrate($connection, $migrations);
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM migration_versions'));
        self::assertSame('分类', $connection->fetchOne('SELECT label FROM taxonomies WHERE handle = ?', ['category']));
        self::assertTrue($connection->tablesExist(['contents', 'migration_versions', 'taxonomy_term_positions', 'users']));

        $runner->migrate($connection, $migrations);
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM migration_versions'));
    }
}
