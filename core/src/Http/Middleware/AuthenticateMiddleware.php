<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Exceptions\AuthenticationException;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthenticateMiddleware implements Middleware
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly ResponseFactory $responses,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        if ($this->auth->userId() !== null) {
            return $next->handle($request);
        }
        if ($this->expectsJson($request)) {
            throw new AuthenticationException();
        }

        return $this->responses->redirect($this->urls->route('login.show'));
    }

    private function expectsJson(ServerRequestInterface $request): bool
    {
        return str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json');
    }
}
