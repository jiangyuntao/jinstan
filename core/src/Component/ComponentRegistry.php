<?php

declare(strict_types=1);

namespace Jinstan\Core\Component;

use InvalidArgumentException;

final class ComponentRegistry implements ComponentRegistrar
{
    /** @var array<string, Component> */
    private array $components = [];

    public function register(Component $component): void
    {
        $this->components[$component->name()] = $component;
    }

    public function get(string $name): Component
    {
        return $this->components[$name] ?? throw new InvalidArgumentException(sprintf('未知组件：%s。', $name));
    }
}
