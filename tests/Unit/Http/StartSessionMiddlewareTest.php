<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Middleware\StartSessionMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class StartSessionMiddlewareTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItStartsSessionBeforeHandlingRequest(): void
    {
        self::assertSame(PHP_SESSION_NONE, session_status());
        $next = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                TestCase::assertSame(PHP_SESSION_ACTIVE, session_status());
                $cookie = session_get_cookie_params();
                TestCase::assertTrue($cookie['httponly']);
                TestCase::assertSame('Lax', $cookie['samesite']);
                TestCase::assertFalse($cookie['secure']);

                return new Response(204);
            }
        };

        $response = (new StartSessionMiddleware())->process(new ServerRequest('GET', '/'), $next);

        self::assertSame(204, $response->getStatusCode());
        session_write_close();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItMarksTheSessionCookieSecureForHttpsRequests(): void
    {
        $next = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $cookie = session_get_cookie_params();
                TestCase::assertTrue($cookie['secure']);
                TestCase::assertTrue($cookie['httponly']);
                TestCase::assertSame('Lax', $cookie['samesite']);

                return new Response(204);
            }
        };

        $response = (new StartSessionMiddleware())->process(new ServerRequest('GET', 'https://example.test/'), $next);

        self::assertSame(204, $response->getStatusCode());
        session_write_close();
    }
}
