<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Exceptions\CsrfTokenMismatchException;
use Jinstan\Core\Http\Middleware\VerifyCsrfTokenMiddleware;
use Jinstan\Core\Security\CsrfToken;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class VerifyCsrfTokenMiddlewareTest extends TestCase
{
    public function testReadRequestDoesNotRequireToken(): void
    {
        $response = (new VerifyCsrfTokenMiddleware(new CsrfToken()))
            ->process(new ServerRequest('GET', '/admin'), $this->destination());

        self::assertSame(204, $response->getStatusCode());
    }

    public function testWriteRequestRejectsMissingToken(): void
    {
        $this->expectException(CsrfTokenMismatchException::class);

        (new VerifyCsrfTokenMiddleware(new CsrfToken()))
            ->process(new ServerRequest('POST', '/admin/content-types'), $this->destination());
    }

    public function testWriteRequestAcceptsBodyAndHeaderTokens(): void
    {
        $csrf = new CsrfToken();
        $middleware = new VerifyCsrfTokenMiddleware($csrf);
        $bodyRequest = (new ServerRequest('POST', '/admin/content-types'))->withParsedBody(['_token' => $csrf->value()]);
        $headerRequest = (new ServerRequest('DELETE', '/admin/content-types/example'))->withHeader('X-CSRF-Token', $csrf->value());

        self::assertSame(204, $middleware->process($bodyRequest, $this->destination())->getStatusCode());
        self::assertSame(204, $middleware->process($headerRequest, $this->destination())->getStatusCode());
    }

    private function destination(): RequestHandler
    {
        return new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };
    }
}
