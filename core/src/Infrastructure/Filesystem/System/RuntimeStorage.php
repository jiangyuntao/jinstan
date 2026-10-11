<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\System;

use Jinstan\Core\Application\System\RuntimeStorageGateway;

final class RuntimeStorage implements RuntimeStorageGateway
{
    public function __construct(private readonly string $storage)
    {
    }

    /** @return list<array{name: string, path: string, size: int}> */
    public function caches(): array
    {
        $base = $this->storage . '/cache';
        $directories = [['name' => 'all', 'path' => $base, 'size' => $this->size($base)]];
        foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $directories[] = [
                'name' => basename($directory),
                'path' => $directory,
                'size' => $this->size($directory),
            ];
        }

        return $directories;
    }

    /** @return array{log: string, size: int} */
    public function logs(): array
    {
        $logPath = $this->storage . '/logs/app.log';

        return [
            'log' => is_file($logPath) ? substr((string) file_get_contents($logPath), -10000) : '',
            'size' => $this->size($logPath),
        ];
    }

    public function clearCache(): void
    {
        $directory = $this->storage . '/cache';
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->getFilename() === '.gitkeep') {
                continue;
            }
            if ($item->isDir()) {
                if (!is_file($item->getPathname() . '/.gitkeep')) {
                    rmdir($item->getPathname());
                }

                continue;
            }

            unlink($item->getPathname());
        }
    }

    public function clearLogs(): void
    {
        file_put_contents($this->storage . '/logs/app.log', '', LOCK_EX);
    }

    private function size(string $path): int
    {
        if (is_file($path)) {
            return (int) filesize($path);
        }
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
