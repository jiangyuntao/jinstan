<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Update;

use Jinstan\Core\Application\Update\CoreUpdateStorage;
use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifest;

final class CoreUpdateFilesystem implements CoreUpdateStorage
{
    public function __construct(private readonly string $projectPath)
    {
    }

    public function assertValidCore(string $core): void
    {
        if (!is_dir($core) || !is_file($core . '/src/Bootstrap/Bootstrap.php')) {
            throw new \InvalidArgumentException('更新包 Core 无效。');
        }
    }

    public function stagedAssets(string $stagedCore, string $release): string
    {
        return dirname($stagedCore) . '/public/assets/core/' . $release;
    }

    public function release(string $core): string
    {
        return CoreAssetManifest::load($core)->release();
    }

    public function corePath(): string
    {
        return $this->projectPath . '/core';
    }

    public function assetReleasePath(string $release): string
    {
        return $this->projectPath . '/public/assets/core/' . $release;
    }

    public function uniqueUpdatePath(string $prefix): string
    {
        return $this->projectPath . '/storage/updates/' . $prefix . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    public function directoryExists(string $path): bool
    {
        return is_dir($path);
    }

    public function move(string $source, string $target, string $error): void
    {
        if (!rename($source, $target)) {
            throw new \RuntimeException($error);
        }
    }

    public function healthy(string $core): bool
    {
        return is_file($core . '/src/Bootstrap/Bootstrap.php');
    }

    public function publishAssets(string $source, string $manifestCore): bool
    {
        $manifest = CoreAssetManifest::load($manifestCore);
        $target = $this->assetReleasePath($manifest->release());
        if (is_dir($target)) {
            $this->verifyAssets($target, $manifestCore);

            return false;
        }

        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new \RuntimeException('无法创建 Core 资源发布目录。');
        }

        $temporary = $parent . '/.staging-' . $manifest->release() . '-' . bin2hex(random_bytes(6));
        try {
            foreach ($manifest->files() as $path => $hash) {
                $sourceFile = $source . '/' . $path;
                $targetFile = $temporary . '/' . $path;
                $directory = dirname($targetFile);
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \RuntimeException('无法创建 Core 资源子目录。');
                }
                if (!copy($sourceFile, $targetFile)) {
                    throw new \RuntimeException(sprintf('无法发布 Core 资源：%s', $path));
                }
            }

            $this->verifyAssets($temporary, $manifestCore);
            $this->move($temporary, $target, '无法启用新 Core 资源版本。');
        } catch (\Throwable $exception) {
            $this->removeDirectory($temporary);
            throw $exception;
        }

        return true;
    }

    public function verifyAssets(string $directory, string $manifestCore): void
    {
        $manifest = CoreAssetManifest::load($manifestCore);
        foreach ($manifest->files() as $path => $expectedHash) {
            $file = $directory . '/' . $path;
            if (!is_file($file) || !hash_equals($expectedHash, (string) hash_file('sha256', $file))) {
                throw new \RuntimeException(sprintf('Core 资源校验失败：%s', $path));
            }
        }
    }

    public function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
