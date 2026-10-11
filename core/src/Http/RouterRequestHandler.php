<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

use Closure;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Exceptions\NotFoundException;
use Jinstan\Core\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RouterRequestHandler implements RequestHandler
{
    private readonly ?Closure $notFoundHandler;

    public function __construct(
        private readonly Router $router,
        ?callable $notFoundHandler = null,
    ) {
        $this->notFoundHandler = $notFoundHandler === null ? null : Closure::fromCallable($notFoundHandler);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->router->dispatch($request);
        if ($response !== null) {
            return $response;
        }

        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)($request);
        }

        throw new NotFoundException();
    }
}
