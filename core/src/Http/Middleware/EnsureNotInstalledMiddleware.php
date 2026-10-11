<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Application\Installer\InstallationLockGateway;
use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EnsureNotInstalledMiddleware implements Middleware
{
    public function __construct(
        private readonly InstallationLockGateway $lock,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        if ($this->lock->exists()) {
            return $this->responses->text('Not Found', 404);
        }

        return $next->handle($request);
    }
}
