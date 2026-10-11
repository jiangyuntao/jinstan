<?php

declare(strict_types=1);

namespace Jinstan\Core\Routing;

use Jinstan\Core\Http\Contracts\Middleware;

/**
 * 面向扩展的受控路由注册入口，不暴露 dispatch 和路由表查询能力。
 */
final class RouteRegistrar
{
    public function __construct(
        private readonly Router $router,
        private readonly ?RouteFileLoader $files = null,
    ) {
    }

    public function get(string $path, mixed $action): Route
    {
        return $this->router->get($path, $action);
    }

    public function post(string $path, mixed $action): Route
    {
        return $this->router->post($path, $action);
    }

    public function has(string $method, string $path): bool
    {
        return $this->router->hasRoute(strtoupper($method), $path);
    }

    public function load(string $file, mixed ...$dependencies): void
    {
        $files = $this->files ?? throw new \LogicException('Route file loader 未配置。');
        $register = $files->load($file);
        if (!is_callable($register)) {
            throw new \RuntimeException(sprintf('Route file "%s" must return a callable.', $file));
        }

        $register($this, ...$dependencies);
    }

    /**
     * @param array{prefix?: string, middleware?: list<Middleware>} $attributes
     * @param callable(self): void $routes
     */
    public function group(array $attributes, callable $routes): void
    {
        $this->router->group($attributes, function () use ($routes): void {
            $routes($this);
        });
    }
}
