<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

interface PluginLifecycle
{
    public function install(): void;

    public function enable(): void;

    public function disable(): void;

    public function upgrade(string $fromVersion): void;

    public function uninstall(): void;

    public function boot(): void;
}
