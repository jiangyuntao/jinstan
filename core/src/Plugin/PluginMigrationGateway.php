<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

interface PluginMigrationGateway
{
    public function migrate(string $plugin, string $pluginPath): void;
}
