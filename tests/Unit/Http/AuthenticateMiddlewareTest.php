<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Exceptions\AuthenticationException;
use Jinstan\Core\Http\Middleware\AuthenticateMiddleware;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Tests\Support\TestUrlGenerator;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthenticateMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testBrowserGuestIsRedirectedToLogin(): void
    {
        $response = $this->middleware($this->authenticator())->process(new ServerRequest('GET', '/admin'), $this->destination());

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testJsonGuestReceivesAuthenticationException(): void
    {
        $this->expectException(AuthenticationException::class);

        $request = (new ServerRequest('GET', '/admin'))->withHeader('Accept', 'application/json');
        $this->middleware($this->authenticator())->process($request, $this->destination());
    }

    public function testAuthenticatedUserContinues(): void
    {
        $auth = $this->authenticator();
        self::assertTrue($auth->attempt('admin@example.test', 'password'));

        $response = $this->middleware($auth)->process(new ServerRequest('GET', '/admin'), $this->destination());

        self::assertSame(204, $response->getStatusCode());
    }

    private function middleware(Authenticator $auth): AuthenticateMiddleware
    {
        return new AuthenticateMiddleware(
            $auth,
            new ResponseFactory(),
            TestUrlGenerator::adminDestinations(),
        );
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

    private function authenticator(): Authenticator
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

        return new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
    }
}
