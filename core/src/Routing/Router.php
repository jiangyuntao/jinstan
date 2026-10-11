<?php

declare(strict_types=1);

namespace Jinstan\Core\Routing;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Pipeline;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;

use function FastRoute\simpleDispatcher;

final class Router
{
    /** @var array<string, Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** @var list<string> */
    private array $prefixStack = [];

    /** @var list<list<Middleware>> */
    private array $middlewareStack = [];

    private ?Dispatcher $dispatcher = null;

    public function __construct(private readonly ?ContainerInterface $container = null)
    {
    }

    public function get(string $path, mixed $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, mixed $handler): Route
    {
        $handler = $this->resolveHandler($handler);
        $path = $this->groupPrefix() . ($path === '/' && $this->groupPrefix() !== '' ? '' : $path);
        $path = $path === '' ? '/' : $path;
        $route = new Route($method, $path, $handler, $this);
        $route->middleware(...$this->groupMiddleware());
        $this->routes[$method . ' ' . $path] = $route;
        $this->dispatcher = null;

        return $route;
    }

    public function name(Route $route, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Route name cannot be empty.');
        }
        if (isset($this->namedRoutes[$name]) && $this->namedRoutes[$name] !== $route) {
            throw new \InvalidArgumentException(sprintf('Duplicate route name: %s', $name));
        }

        $previous = $route->routeName();
        if ($previous !== null && $previous !== $name) {
            unset($this->namedRoutes[$previous]);
        }

        $this->namedRoutes[$name] = $route;
    }

    public function named(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * @param array{prefix?: string, middleware?: list<Middleware>} $attributes
     * @param callable(self): void $routes
     */
    public function group(array $attributes, callable $routes): void
    {
        $prefix = $this->normalizePrefix((string) ($attributes['prefix'] ?? ''));
        $middleware = $attributes['middleware'] ?? [];
        foreach ($middleware as $item) {
            if (!$item instanceof Middleware) {
                throw new \InvalidArgumentException('Route group middleware must implement Middleware.');
            }
        }

        $this->prefixStack[] = $prefix;
        $this->middlewareStack[] = $middleware;
        try {
            $routes($this);
        } finally {
            array_pop($this->prefixStack);
            array_pop($this->middlewareStack);
        }
    }

    public function hasRoute(string $method, string $path): bool
    {
        return isset($this->routes[$method . ' ' . $path]);
    }

    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        $path = $request->getUri()->getPath();
        // 后台链接和浏览器手动输入都应接受末尾斜杠，避免同一资源出现 404。
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $routeInfo = $this->dispatcher()->dispatch($request->getMethod(), $path);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return null;
        }

        /** @var Route $route */
        $route = $routeInfo[1];
        $parameters = $routeInfo[2];
        $destination = new class ($route, $parameters) implements RequestHandler {
            /** @param array<string, string> $parameters */
            public function __construct(
                private readonly Route $route,
                private readonly array $parameters,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->route->handler())($request, $this->parameters);
            }
        };

        return (new Pipeline($route->middlewareStack(), $destination))->handle($request);
    }

    private function dispatcher(): Dispatcher
    {
        return $this->dispatcher ??= simpleDispatcher(function (RouteCollector $collector): void {
            foreach ($this->routes as $route) {
                $collector->addRoute($route->method, $route->path, $route);
            }
        });
    }

    private function groupPrefix(): string
    {
        return implode('', $this->prefixStack);
    }

    /** @return list<Middleware> */
    private function groupMiddleware(): array
    {
        return array_merge(...$this->middlewareStack);
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '' || $prefix === '/') {
            return '';
        }

        return '/' . trim($prefix, '/');
    }

    /** @return callable(ServerRequestInterface, array<string, string>): ResponseInterface */
    private function resolveHandler(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (
            is_array($handler)
            && count($handler) === 2
            && is_string($handler[0])
            && class_exists($handler[0])
            && is_string($handler[1])
            && $handler[1] !== ''
        ) {
            return new ControllerAction([$handler[0], $handler[1]], $this->container);
        }

        throw new \InvalidArgumentException('Route action must be callable or a [Controller::class, method] pair.');
    }
}
