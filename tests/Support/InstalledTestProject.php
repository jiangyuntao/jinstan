<?php

declare(strict_types=1);

namespace Jinstan\Tests\Support;

use Jinstan\Core\Application\Installer\InstallationInput;
use Jinstan\Core\Application\Installer\Services\InstallApplicationService;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Filesystem\Installer\DatabaseConfigWriter;
use Jinstan\Core\Infrastructure\Filesystem\Installer\InstallationLock;
use Jinstan\Core\Infrastructure\Persistence\Installer\InstallationDatabaseInstaller;
use Jinstan\Core\Infrastructure\Persistence\Installer\InstallationSeeder;
use RuntimeException;

final class InstalledTestProject
{
    private const ADMIN_EMAIL = 'admin@example.test';
    private const ADMIN_PASSWORD = 'baseline-password';

    private function __construct(public readonly string $path)
    {
    }

    public static function create(bool $installed = true): self
    {
        $source = dirname(__DIR__, 2);
        $path = sys_get_temp_dir() . '/jinstan-baseline-' . bin2hex(random_bytes(8));

        foreach (['config', 'storage/database', 'storage/logs', 'storage/cache'] as $directory) {
            if (!mkdir($path . '/' . $directory, 0750, true) && !is_dir($path . '/' . $directory)) {
                throw new RuntimeException('无法创建测试项目目录。');
            }
        }

        foreach (['core', 'plugins', 'themes'] as $directory) {
            if (!symlink($source . '/' . $directory, $path . '/' . $directory)) {
                throw new RuntimeException(sprintf('无法链接测试项目目录：%s。', $directory));
            }
        }

        file_put_contents(
            $path . '/config/app.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['app' => ['debug' => false, 'name' => 'Jinstan']];\n",
        );
        file_put_contents(
            $path . '/config/database.php',
            "<?php\n\ndeclare(strict_types=1);\n\n\$file = dirname(__DIR__) . '/storage/config/database.php';\n\nreturn is_file(\$file) ? require \$file : [];\n",
        );

        $project = new self($path);
        if ($installed) {
            $project->install();
        }

        return $project;
    }

    public function connection(): Connection
    {
        return (new ConnectionFactory())->create([
            'driver' => 'sqlite',
            'path' => $this->path . '/storage/database/database.sqlite',
        ]);
    }

    public function adminEmail(): string
    {
        return self::ADMIN_EMAIL;
    }

    public function adminPassword(): string
    {
        return self::ADMIN_PASSWORD;
    }

    private function install(): void
    {
        $lock = new InstallationLock($this->path . '/storage/installed.php');
        $installer = new InstallApplicationService(
            new InstallationDatabaseInstaller(
                new ConnectionFactory(),
                new MigrationRunner(),
                new InstallationSeeder(),
                $this->path . '/core/src/Database/Migrations',
            ),
            $lock,
            new InstallationInput($this->path),
            new DatabaseConfigWriter($this->path),
        );
        $installer->execute([
            'site_name' => 'Baseline Site',
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
            'database_driver' => 'sqlite',
        ]);
    }
}
