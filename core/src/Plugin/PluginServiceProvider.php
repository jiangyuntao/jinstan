<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

interface PluginServiceProvider extends PluginLifecycle
{
    public function register(PluginRegistrar $registrar): void;
}
