<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Routing;

use Jinstan\Core\Routing\RouteFileLoader;

final class LocalRouteFileLoader implements RouteFileLoader
{
    public function load(string $file): mixed
    {
        $path = realpath($file);
        if ($path === false || !is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            throw new \InvalidArgumentException(sprintf('Route file does not exist or is not PHP: %s', $file));
        }

        return require $path;
    }
}
