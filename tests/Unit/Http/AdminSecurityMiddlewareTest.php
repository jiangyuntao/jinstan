<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\ExceptionHandler;
use Jinstan\Core\Http\Kernel;
use Jinstan\Core\Http\Middleware\AdminSecurityMiddleware;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Tests\Support\TestUrlGenerator;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;

final class AdminSecurityMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testGuestBrowserRequestRedirectsToLogin(): void
    {
        $response = $this->kernel()->handle(new ServerRequest('GET', '/admin'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testGuestJsonRequestReceivesAuthenticationError(): void
    {
        $request = (new ServerRequest('GET', '/admin'))->withHeader('Accept', 'application/json');
        $response = $this->kernel()->handle($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['message' => 'Unauthenticated'], json_decode((string) $response->getBody(), true));
    }

    public function testAuthenticatedWriteRequiresValidCsrfToken(): void
    {
        [$kernel, $auth, $csrf] = $this->services();
        self::assertTrue($auth->attempt('admin@example.test', 'password'));

        $invalid = $kernel->handle((new ServerRequest('POST', '/admin/settings'))->withParsedBody([]));
        self::assertSame(419, $invalid->getStatusCode());

        $valid = $kernel->handle((new ServerRequest('POST', '/admin/settings'))->withParsedBody([
            '_token' => $csrf->value(),
        ]));
        self::assertSame(204, $valid->getStatusCode());
    }

    public function testPublicRequestPassesWithoutAuthentication(): void
    {
        self::assertSame(204, $this->kernel()->handle(new ServerRequest('GET', '/'))->getStatusCode());
    }

    private function kernel(): Kernel
    {
        return $this->services()[0];
    }

    /** @return array{Kernel, Authenticator, CsrfToken} */
    private function services(): array
    {
        $_SESSION = [];
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 3) . '/core/src/Database/Migrations');
        $connection->insert('users', [
            'email' => 'admin@example.test',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
        $csrf = new CsrfToken();
        $destination = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };
        $responses = new ResponseFactory();
        $kernel = new Kernel(
            $destination,
            new ExceptionHandler(new NullLogger(), false, $responses),
            [new AdminSecurityMiddleware($auth, $csrf, $responses, TestUrlGenerator::with(['login.show' => '/login']))],
        );

        return [$kernel, $auth, $csrf];
    }
}
