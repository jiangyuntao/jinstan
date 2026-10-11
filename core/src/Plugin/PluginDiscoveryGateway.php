<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

interface PluginDiscoveryGateway
{
    /** @return list<array{manifest_path: string, manifest: array<string, mixed>}> */
    public function discover(string $pluginsPath): array;

    public function loadEntry(string $manifestPath, string $class, string $name): PluginServiceProvider;

    public function optionalDirectory(string $pluginPath, string $relativePath): ?string;
}
