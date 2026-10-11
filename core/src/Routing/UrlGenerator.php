<?php

declare(strict_types=1);

namespace Jinstan\Core\Routing;

final class UrlGenerator
{
    public function __construct(private readonly Router $router)
    {
    }

    /** @param array<string, mixed> $parameters */
    public function route(string $name, array $parameters = []): string
    {
        $route = $this->router->named($name);
        if ($route === null) {
            throw new \InvalidArgumentException(sprintf('Unknown route name: %s', $name));
        }

        $used = [];
        $path = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^{}]+)?\}/',
            static function (array $matches) use ($parameters, &$used): string {
                $key = $matches[1];
                if (!array_key_exists($key, $parameters) || $parameters[$key] === null) {
                    throw new \InvalidArgumentException(sprintf('Missing route parameter: %s', $key));
                }
                $used[$key] = true;

                return rawurlencode((string) $parameters[$key]);
            },
            $route->path,
        );
        if (!is_string($path)) {
            throw new \RuntimeException('Route URL could not be generated.');
        }

        $query = array_diff_key($parameters, $used);

        return $query === [] ? $path : $path . '?' . http_build_query($query);
    }
}
