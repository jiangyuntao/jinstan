<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Update\CoreMigrationGateway;
use Jinstan\Core\Application\Update\Services\CoreUpdater as ApplicationCoreUpdater;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\CoreMigrationRunner;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Filesystem\Update\CoreUpdateFilesystem;
use Jinstan\Core\Infrastructure\Filesystem\Update\MaintenanceMode;
use PHPUnit\Framework\TestCase;

final class CoreUpdaterTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/jinstan-core-update-' . bin2hex(random_bytes(6));
        $this->makeDirectory($this->workspace . '/core/src/Bootstrap');
        $this->makeDirectory($this->workspace . '/storage/updates');
        file_put_contents($this->workspace . '/core/src/Bootstrap/Bootstrap.php', '<?php // old core');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testCoreAssetsArePublishedWithTheCoreRelease(): void
    {
        $release = $this->stageRelease();

        $database = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($database, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $maintenance = new MaintenanceMode($this->workspace . '/storage/maintenance.lock');
        $updater = new ApplicationCoreUpdater(
            $maintenance,
            new CoreMigrationRunner(new MigrationRunner(), $database),
            new CoreUpdateFilesystem($this->workspace),
        );

        $updater->apply($release . '/core');

        self::assertFileExists($this->workspace . '/public/assets/core/2.0.0/admin.css');
        self::assertStringContainsString('new core', (string) file_get_contents($this->workspace . '/core/src/Bootstrap/Bootstrap.php'));
        self::assertFalse($maintenance->enabled());
    }

    public function testMigrationFailureRestoresCoreAndPreservesExtensionDirectories(): void
    {
        foreach (['site', 'plugins', 'themes'] as $directory) {
            $this->makeDirectory($this->workspace . '/' . $directory);
            file_put_contents($this->workspace . '/' . $directory . '/keep.txt', $directory);
        }
        file_put_contents($this->workspace . '/storage/keep.txt', 'storage');
        $release = $this->stageRelease();
        $maintenance = new MaintenanceMode($this->workspace . '/storage/maintenance.lock');
        $migrations = new class () implements CoreMigrationGateway {
            public function migrate(string $path): void
            {
                throw new \RuntimeException('Migration failed.');
            }
        };
        $updater = new ApplicationCoreUpdater(
            $maintenance,
            $migrations,
            new CoreUpdateFilesystem($this->workspace),
        );

        try {
            $updater->apply($release . '/core');
            self::fail('A failed migration must abort the Core update.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Migration failed.', $exception->getMessage());
        }

        self::assertSame('<?php // old core', file_get_contents($this->workspace . '/core/src/Bootstrap/Bootstrap.php'));
        self::assertFileDoesNotExist($this->workspace . '/public/assets/core/2.0.0/admin.css');
        self::assertFalse($maintenance->enabled());
        foreach (['site', 'plugins', 'themes', 'storage'] as $directory) {
            self::assertFileExists($this->workspace . '/' . $directory . '/keep.txt');
        }
    }

    private function stageRelease(): string
    {
        $release = $this->workspace . '/release';
        $this->makeDirectory($release . '/core/src/Bootstrap');
        $this->makeDirectory($release . '/core/src/Database/Migrations');
        $this->makeDirectory($release . '/core/assets');
        $this->makeDirectory($release . '/public/assets/core/2.0.0');
        file_put_contents($release . '/core/src/Bootstrap/Bootstrap.php', '<?php // new core');
        file_put_contents($release . '/core/src/Version.php', "<?php final class Version { public const CURRENT = '2.0.0'; }");
        file_put_contents($release . '/public/assets/core/2.0.0/admin.css', 'new-admin-css');
        $hash = hash_file('sha256', $release . '/public/assets/core/2.0.0/admin.css');
        file_put_contents($release . '/core/assets/manifest.php', sprintf(
            "<?php return ['release' => '2.0.0', 'assets' => ['admin.css' => 'admin.css'], 'files' => ['admin.css' => '%s']];",
            $hash,
        ));

        return $release;
    }

    private function makeDirectory(string $path): void
    {
        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('无法创建测试目录：%s', $path));
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
