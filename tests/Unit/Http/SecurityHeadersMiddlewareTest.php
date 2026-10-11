<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\ExceptionHandler;
use Jinstan\Core\Http\Middleware\SecurityHeadersMiddleware;
use Jinstan\Core\Http\ResponseFactory;
use Monolog\Logger;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testItAddsSecurityHeadersToSuccessfulResponses(): void
    {
        $next = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $response = $this->middleware()->process(new ServerRequest('GET', '/'), $next);

        $this->assertHeaders($response);
    }

    public function testItAddsSecurityHeadersToConvertedExceptionResponses(): void
    {
        $next = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Sensitive detail');
            }
        };

        $response = $this->middleware()->process(new ServerRequest('GET', '/'), $next);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', (string) $response->getBody());
        $this->assertHeaders($response);
    }

    public function testItDisablesCachingForAdminAndEnablesHstsOnHttps(): void
    {
        $next = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $response = $this->middleware()->process(new ServerRequest('GET', 'https://example.test/admin'), $next);

        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('no-cache', $response->getHeaderLine('Pragma'));
        self::assertSame('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
    }

    private function middleware(): SecurityHeadersMiddleware
    {
        return new SecurityHeadersMiddleware(
            new ExceptionHandler(new Logger('test'), false, new ResponseFactory()),
        );
    }

    private function assertHeaders(ResponseInterface $response): void
    {
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame(
            "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'",
            $response->getHeaderLine('Content-Security-Policy'),
        );
        self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('camera=(), microphone=(), geolocation=()', $response->getHeaderLine('Permissions-Policy'));
    }
}
