<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Installer;

use Jinstan\Core\Application\Installer\DatabaseConfigGateway;

final class DatabaseConfigWriter implements DatabaseConfigGateway
{
    public function __construct(private readonly string $projectPath)
    {
    }

    /** @param array<string, mixed> $config */
    public function write(array $config): void
    {
        $directory = $this->projectPath . '/storage/config';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('无法创建数据库配置目录。');
        }

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        $written = file_put_contents($directory . '/database.php', $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            throw new \RuntimeException('无法写入数据库配置。');
        }
        if (!chmod($directory . '/database.php', 0600)) {
            throw new \RuntimeException('无法收紧数据库配置权限。');
        }
    }
}
