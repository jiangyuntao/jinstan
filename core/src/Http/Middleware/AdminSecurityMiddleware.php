<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Exceptions\AuthenticationException;
use Jinstan\Core\Http\Exceptions\CsrfTokenMismatchException;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminSecurityMiddleware implements Middleware
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly CsrfToken $csrf,
        private readonly ResponseFactory $responses,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        if (!$this->isAdminRequest($request)) {
            return $next->handle($request);
        }

        if ($this->auth->userId() === null) {
            if ($this->expectsJson($request)) {
                throw new AuthenticationException();
            }

            return $this->responses->redirect($this->urls->route('login.show'));
        }

        if ($this->requiresCsrfValidation($request) && !$this->csrf->valid($this->csrfToken($request))) {
            throw new CsrfTokenMismatchException('CSRF token is invalid.');
        }

        return $next->handle($request);
    }

    private function isAdminRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    private function requiresCsrfValidation(ServerRequestInterface $request): bool
    {
        return !in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function csrfToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('X-CSRF-Token');
        if ($header !== '') {
            return $header;
        }

        $input = $request->getParsedBody();
        $token = is_array($input) ? ($input['_token'] ?? null) : null;

        return is_string($token) ? $token : null;
    }

    private function expectsJson(ServerRequestInterface $request): bool
    {
        return str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json');
    }
}
