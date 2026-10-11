<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Plugin;

use Jinstan\Core\Plugin\PluginServiceProvider;
use Jinstan\Core\Plugin\PluginDiscoveryGateway;
use RuntimeException;

final class PluginDiscovery implements PluginDiscoveryGateway
{
    /** @return list<array{manifest_path: string, manifest: array<string, mixed>}> */
    public function discover(string $pluginsPath): array
    {
        $plugins = [];

        foreach (glob(rtrim($pluginsPath, '/') . '/*/plugin.json') ?: [] as $manifestPath) {
            $manifest = json_decode(
                (string) file_get_contents($manifestPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (!is_array($manifest)) {
                throw new RuntimeException(sprintf('插件 Manifest 无效：%s。', $manifestPath));
            }

            $plugins[] = [
                'manifest_path' => $manifestPath,
                'manifest' => $manifest,
            ];
        }

        return $plugins;
    }

    public function loadEntry(string $manifestPath, string $class, string $name): PluginServiceProvider
    {
        $file = dirname($manifestPath) . '/Plugin.php';
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('插件入口不存在：%s。', $name));
        }

        require_once $file;
        $plugin = new $class();
        if (!$plugin instanceof PluginServiceProvider) {
            throw new RuntimeException(sprintf('插件 %s 未实现公开 PluginServiceProvider 接口。', $name));
        }

        return $plugin;
    }

    public function optionalDirectory(string $pluginPath, string $relativePath): ?string
    {
        $path = rtrim($pluginPath, '/') . '/' . ltrim($relativePath, '/');

        return is_dir($path) ? $path : null;
    }
}
