<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Plugin\Queries;

use Jinstan\Core\Application\Plugin\Services\PluginManager;

final class PluginAdminQuery
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    /** @return list<array<string, mixed>> */
    public function plugins(): array
    {
        return $this->plugins->plugins();
    }

    /** @return array<string, mixed> */
    public function settings(string $name): array
    {
        $plugin = $this->plugins->plugin($name);
        if ($plugin === null || !$plugin['installed'] || $plugin['settings'] === []) {
            throw new \OutOfBoundsException('Plugin settings not found.');
        }

        return $plugin;
    }
}
