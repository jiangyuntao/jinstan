<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

interface PluginViewRegistrar
{
    public function register(string $namespace, string $path): void;
}
