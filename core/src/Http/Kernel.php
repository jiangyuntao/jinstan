<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class Kernel implements RequestHandler
{
    /** @param list<Middleware> $middleware */
    public function __construct(
        private readonly RequestHandler $router,
        private readonly ExceptionHandler $exceptions,
        private readonly array $middleware = [],
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return (new Pipeline($this->middleware, $this->router))->handle($request);
        } catch (Throwable $exception) {
            return $this->exceptions->render($exception, $request);
        }
    }
}
