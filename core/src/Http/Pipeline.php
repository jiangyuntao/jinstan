<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Pipeline implements RequestHandler
{
    /** @param list<Middleware> $middleware */
    public function __construct(
        private readonly array $middleware,
        private readonly RequestHandler $destination,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = array_reduce(
            array_reverse($this->middleware),
            static fn (RequestHandler $next, Middleware $middleware): RequestHandler => new class ($middleware, $next) implements RequestHandler {
                public function __construct(
                    private readonly Middleware $middleware,
                    private readonly RequestHandler $next,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            },
            $this->destination,
        );

        return $handler->handle($request);
    }
}
