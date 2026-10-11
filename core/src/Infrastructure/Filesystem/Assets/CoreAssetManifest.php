<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Assets;

final class CoreAssetManifest
{
    /**
     * @param array<string, string> $assets
     * @param array<string, string> $files
     */
    private function __construct(
        private readonly string $release,
        private readonly array $assets,
        private readonly array $files,
    ) {
    }

    public static function load(string $corePath): self
    {
        $manifestPath = rtrim($corePath, '/') . '/assets/manifest.php';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Core 资源清单不存在。');
        }

        $manifest = require $manifestPath;
        if (!is_array($manifest)) {
            throw new \RuntimeException('Core 资源清单格式无效。');
        }

        $release = $manifest['release'] ?? null;
        $assets = $manifest['assets'] ?? null;
        $files = $manifest['files'] ?? null;
        if (!is_string($release) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $release) !== 1) {
            throw new \RuntimeException('Core 资源版本无效。');
        }
        $versionPath = rtrim($corePath, '/') . '/src/Version.php';
        $versionSource = is_file($versionPath) ? file_get_contents($versionPath) : false;
        if (!is_string($versionSource)
            || preg_match("/CURRENT\\s*=\\s*'([^']+)'/", $versionSource, $matches) !== 1
            || $matches[1] !== $release
        ) {
            throw new \RuntimeException('Core 代码版本与资源版本不一致。');
        }
        if (!is_array($assets) || !is_array($files)) {
            throw new \RuntimeException('Core 资源清单缺少资源映射或文件校验值。');
        }

        self::assertEntries($assets, false);
        self::assertEntries($files, true);
        foreach ($assets as $path) {
            if (!isset($files[$path])) {
                throw new \RuntimeException('Core 资源映射未包含文件校验值。');
            }
        }

        /** @var array<string, string> $assets */
        /** @var array<string, string> $files */
        return new self($release, $assets, $files);
    }

    public function release(): string
    {
        return $this->release;
    }

    public function url(string $name): string
    {
        $path = $this->assets[$name] ?? null;
        if ($path === null) {
            throw new \InvalidArgumentException(sprintf('未知的 Core 资源：%s', $name));
        }

        return '/assets/core/' . rawurlencode($this->release) . '/' . $path;
    }

    /** @return array<string, string> */
    public function files(): array
    {
        return $this->files;
    }

    /** @param array<mixed> $entries */
    private static function assertEntries(array $entries, bool $hashes): void
    {
        foreach ($entries as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value) || $value === '') {
                throw new \RuntimeException('Core 资源清单条目无效。');
            }
            if ($hashes && preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
                throw new \RuntimeException('Core 资源文件校验值无效。');
            }

            $path = $hashes ? $name : $value;
            if (str_starts_with($path, '/') || str_contains($path, '..') || preg_match('#^[A-Za-z0-9._/-]+$#', $path) !== 1) {
                throw new \RuntimeException('Core 资源文件路径无效。');
            }
        }
    }
}
