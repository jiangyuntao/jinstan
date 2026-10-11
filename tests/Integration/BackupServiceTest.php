<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Filesystem\Backup\BackupService;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use PHPUnit\Framework\TestCase;

final class BackupServiceTest extends TestCase
{
    public function testItListsAndDeletesAnExistingBackup(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-backup-' . bin2hex(random_bytes(6));
        mkdir($projectPath . '/storage/database', 0750, true);
        mkdir($projectPath . '/storage/config', 0750, true);
        touch($projectPath . '/storage/database/database.sqlite');

        $connection = (new ConnectionFactory())->create([
            'driver' => 'sqlite',
            'path' => $projectPath . '/storage/database/database.sqlite',
        ]);
        $service = new BackupService($connection, $projectPath);
        $id = $service->create();

        self::assertSame($id, $service->all()[0]['id']);

        $service->delete($id);

        self::assertSame([], $service->all());
        self::assertDirectoryDoesNotExist($projectPath . '/storage/backups/' . $id);
    }

    public function testItRejectsAnUnsafeBackupIdentifier(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        $service = new BackupService($connection, sys_get_temp_dir());

        $this->expectException(\InvalidArgumentException::class);

        $service->delete('../database');
    }

    public function testItPaginatesBackupHistory(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-backup-pages-' . bin2hex(random_bytes(6));
        $backupPath = $projectPath . '/storage/backups';
        mkdir($backupPath, 0750, true);

        foreach (range(1, 21) as $offset) {
            $id = sprintf('202609120000%02d', $offset);
            mkdir($backupPath . '/' . $id, 0750);
            file_put_contents($backupPath . '/' . $id . '/manifest.json', json_encode([
                'created_at' => '2026-09-12T00:00:00+00:00',
                'database_sha256' => str_repeat((string) ($offset % 10), 64),
            ], JSON_THROW_ON_ERROR));
        }

        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        $pagination = (new BackupService($connection, $projectPath))->paginate(2);

        self::assertSame(21, $pagination['total']);
        self::assertSame(2, $pagination['page']);
        self::assertSame(2, $pagination['pages']);
        self::assertCount(1, $pagination['items']);
        self::assertSame('20260912000001', $pagination['items'][0]['id']);
    }
}
