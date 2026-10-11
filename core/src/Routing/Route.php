<?php

declare(strict_types=1);

namespace Jinstan\Core\Routing;

use Jinstan\Core\Http\Contracts\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Route
{
    private ?string $name = null;

    /** @var list<Middleware> */
    private array $middleware = [];

    /** @param callable(ServerRequestInterface, array<string, string>): ResponseInterface $handler */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly mixed $handler,
        private readonly Router $router,
    ) {
    }

    public function name(string $name): self
    {
        $this->router->name($this, $name);
        $this->name = $name;

        return $this;
    }

    public function routeName(): ?string
    {
        return $this->name;
    }

    public function middleware(Middleware ...$middleware): self
    {
        $this->middleware = [...$this->middleware, ...$middleware];

        return $this;
    }

    /** @return list<Middleware> */
    public function middlewareStack(): array
    {
        return $this->middleware;
    }

    /** @return callable(ServerRequestInterface, array<string, string>): ResponseInterface */
    public function handler(): callable
    {
        return $this->handler;
    }
}
