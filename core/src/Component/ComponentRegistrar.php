<?php

declare(strict_types=1);

namespace Jinstan\Core\Component;

interface ComponentRegistrar
{
    public function register(Component $component): void;
}
