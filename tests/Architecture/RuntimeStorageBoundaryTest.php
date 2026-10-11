<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RuntimeStorageBoundaryTest extends TestCase
{
    public function testProductionRuntimePathsRemainInsideStorage(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src';
        $sources = [
            'Providers/FoundationServiceProvider.php' => [
                "projectPath('storage/logs/app.log')",
                "projectPath('storage/maintenance.lock')",
            ],
            'Providers/InstallerServiceProvider.php' => [
                "projectPath('storage/installed.php')",
                "projectPath('storage/cache/latte-installer')",
            ],
            'Providers/ContentServiceProvider.php' => [
                "projectPath('storage/content-attachments')",
            ],
            'Providers/AdminServiceProvider.php' => [
                "projectPath('storage/cache/latte-admin')",
                "projectPath('storage')",
            ],
            'Infrastructure/View/ThemeRenderer.php' => [
                "'/storage/cache/latte'",
                "'/storage/cache/latte-html'",
            ],
            'Infrastructure/Filesystem/Backup/BackupService.php' => [
                "'/storage/backups'",
                "'/storage/database/database.sqlite'",
                "'/storage/config/database.php'",
            ],
            'Infrastructure/Filesystem/Installer/DatabaseConfigWriter.php' => [
                "'/storage/config'",
            ],
            'Infrastructure/Filesystem/Update/CoreUpdateFilesystem.php' => [
                "'/storage/updates/'",
            ],
            'Application/Installer/InstallationInput.php' => [
                "'/storage/database/database.sqlite'",
            ],
        ];

        foreach ($sources as $file => $paths) {
            $source = (string) file_get_contents($root . '/' . $file);
            foreach ($paths as $path) {
                self::assertStringContainsString($path, $source, $file);
            }
        }
    }

    public function testProductionCodeDoesNotUseSystemTemporaryDirectories(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src';
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains((string) file_get_contents($file->getPathname()), 'sys_get_temp_dir(')) {
                $violations[] = str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname());
            }
        }

        self::assertSame([], $violations);
    }
}
