<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Installer;

use Jinstan\Core\Application\Installer\InstallationLockGateway;

final class InstallationLock implements InstallationLockGateway
{
    public function __construct(private readonly string $path)
    {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function create(): void
    {
        $contents = "<?php\n\nreturn true;\n";
        $written = file_put_contents($this->path, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            throw new \RuntimeException('无法创建安装锁。');
        }
        @chmod($this->path, 0600);
    }
}
