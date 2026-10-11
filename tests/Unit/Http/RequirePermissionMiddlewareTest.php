<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Middleware\RequirePermissionMiddleware;
use Jinstan\Core\Http\ResponseFactory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RequirePermissionMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testItRejectsAUserWithoutTheRequiredPermission(): void
    {
        $auth = $this->authenticator('editor');
        $response = $this->middleware($auth)->process(new ServerRequest('GET', '/admin/content-types'), $this->destination());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testItAllowsAUserWithTheRequiredPermission(): void
    {
        $auth = $this->authenticator('administrator');
        $response = $this->middleware($auth)->process(new ServerRequest('GET', '/admin/content-types'), $this->destination());

        self::assertSame(204, $response->getStatusCode());
    }

    private function middleware(Authenticator $auth): RequirePermissionMiddleware
    {
        return new RequirePermissionMiddleware($auth, new ResponseFactory(), 'content.manage');
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

    private function authenticator(string $role): Authenticator
    {
        $_SESSION = [];
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 3) . '/core/src/Database/Migrations');
        if ($role !== 'administrator') {
            $connection->insert('roles', ['role_key' => $role, 'label' => '编辑']);
        }
        $connection->insert('users', [
            'email' => $role . '@example.test',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => $role]);
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
        self::assertTrue($auth->attempt($role . '@example.test', 'password'));

        return $auth;
    }
}
