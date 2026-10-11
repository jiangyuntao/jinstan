<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RequirePermissionMiddleware implements Middleware
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly ResponseFactory $responses,
        private readonly string $permission,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        if (!$this->auth->can($this->permission)) {
            return $this->responses->text('Forbidden', 403);
        }

        return $next->handle($request);
    }
}
