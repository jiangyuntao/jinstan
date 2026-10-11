<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Exceptions\CsrfTokenMismatchException;
use Jinstan\Core\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class VerifyCsrfTokenMiddleware implements Middleware
{
    public function __construct(private readonly CsrfToken $csrf)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        if ($this->isReadRequest($request) || $this->csrf->valid($this->token($request))) {
            return $next->handle($request);
        }

        throw new CsrfTokenMismatchException('CSRF token is invalid.');
    }

    private function isReadRequest(ServerRequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function token(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('X-CSRF-Token');
        if ($header !== '') {
            return $header;
        }

        $input = $request->getParsedBody();
        $token = is_array($input) ? ($input['_token'] ?? null) : null;

        return is_string($token) ? $token : null;
    }
}
