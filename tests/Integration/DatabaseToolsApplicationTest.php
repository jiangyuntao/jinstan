<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Database\DatabaseOperationsAdapter;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\DatabaseToolsPresenter;
use Jinstan\Core\Application\Database\Queries\DatabaseToolsQuery;
use Jinstan\Core\Application\Database\Services\DatabaseMaintenance;
use Jinstan\Core\Infrastructure\Filesystem\Backup\BackupService;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class DatabaseToolsApplicationTest extends TestCase
{
    public function testItBuildsDatabaseStatusAndNormalizesInvalidBackupPage(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $projectPath = sys_get_temp_dir() . '/jinstan-database-tools-' . bin2hex(random_bytes(6));
        mkdir($projectPath . '/storage/backups', 0750, true);
        $query = new DatabaseToolsQuery(
            new DatabaseOperationsAdapter($connection),
            new BackupService($connection, $projectPath),
        );
        $presenter = new DatabaseToolsPresenter(
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                sys_get_temp_dir() . '/jinstan-database-tools-presenter',
                TestUrlGenerator::adminViews(),
            ),
            new CsrfToken(),
        );

        $data = $query->execute(['backup_page' => ['invalid']]);
        $view = $presenter->present($data);

        self::assertSame('sqlite', $data['driver']);
        self::assertGreaterThan(0, $data['migrations']);
        self::assertSame(['value' => 'ok', 'translation' => null], $data['integrity']);
        self::assertSame(1, $data['backupPage']);
        self::assertSame(0, $data['backupTotal']);
        self::assertSame('database-tools', $view->template);
        self::assertSame('ok', $view->data['integrity']);
        self::assertNotSame('', $view->data['csrfToken']);
    }

    public function testMaintenanceOptimizesAndCoordinatesBackupLifecycle(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-database-maintenance-' . bin2hex(random_bytes(6));
        mkdir($projectPath . '/storage/database', 0750, true);
        $connection = (new ConnectionFactory())->create([
            'driver' => 'sqlite',
            'path' => $projectPath . '/storage/database/database.sqlite',
        ]);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $maintenance = new DatabaseMaintenance(
            new DatabaseOperationsAdapter($connection),
            new BackupService($connection, $projectPath),
        );

        $maintenance->optimize();
        $connection->insert('redirects', [
            'source_path' => '/loop',
            'target_path' => '/loop',
            'status_code' => 301,
        ]);
        self::assertSame(1, $maintenance->cleanup());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM redirects'));
        $id = $maintenance->createBackup();

        self::assertMatchesRegularExpression('/^[0-9]{14}$/', $id);
        self::assertFileExists($projectPath . '/storage/backups/' . $id . '/database.sqlite');
        self::assertFileExists($projectPath . '/storage/backups/' . $id . '/manifest.json');

        $maintenance->deleteBackup($id);
        self::assertDirectoryDoesNotExist($projectPath . '/storage/backups/' . $id);
    }
}
