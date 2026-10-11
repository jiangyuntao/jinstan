<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Middleware\EnsureNotInstalledMiddleware;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Infrastructure\Filesystem\Installer\InstallationLock;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EnsureNotInstalledMiddlewareTest extends TestCase
{
    public function testItPassesBeforeInstallationAndReturnsNotFoundAfterLock(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-install-lock-' . bin2hex(random_bytes(8)) . '.php';
        $lock = new InstallationLock($path);
        $middleware = new EnsureNotInstalledMiddleware($lock, new ResponseFactory());
        $next = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };

        try {
            self::assertSame(204, $middleware->process(new ServerRequest('GET', '/install'), $next)->getStatusCode());
            $lock->create();
            $response = $middleware->process(new ServerRequest('GET', '/install'), $next);
            self::assertSame(404, $response->getStatusCode());
            self::assertSame('Not Found', (string) $response->getBody());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
