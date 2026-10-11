<?php

declare(strict_types=1);

namespace Jinstan\Core\Routing;

interface RouteFileLoader
{
    public function load(string $file): mixed;
}
