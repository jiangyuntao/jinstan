<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SecurityHeadersMiddleware implements Middleware
{
    public function __construct(private readonly ExceptionHandler $exceptions)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        try {
            $response = $next->handle($request);
        } catch (\Throwable $exception) {
            // 最外层处理异常，确保错误响应与正常响应使用同一组安全头。
            $response = $this->exceptions->render($exception, $request);
        }

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader(
                'Content-Security-Policy',
                "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'",
            )
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($this->isSensitivePage($request)) {
            $response = $response
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        }
        if (strtolower($request->getUri()->getScheme()) === 'https') {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000',
            );
        }

        return $response;
    }

    private function isSensitivePage(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return $path === '/login'
            || $path === '/logout'
            || $path === '/install'
            || $path === '/admin'
            || str_starts_with($path, '/admin/');
    }
}
