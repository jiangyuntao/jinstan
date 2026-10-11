<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\Middleware\MaintenanceModeMiddleware;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Infrastructure\Filesystem\Update\MaintenanceMode;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MaintenanceModeMiddlewareTest extends TestCase
{
    public function testItPassesNormallyAndReturnsRetryableServiceUnavailableDuringMaintenance(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-maintenance-' . bin2hex(random_bytes(8)) . '.lock';
        $maintenance = new MaintenanceMode($path);
        $middleware = new MaintenanceModeMiddleware($maintenance, new ResponseFactory(), ['/health']);
        $next = new class () implements RequestHandler {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };

        try {
            self::assertSame(204, $middleware->process(new ServerRequest('GET', '/'), $next)->getStatusCode());

            $maintenance->enable();
            $response = $middleware->process(new ServerRequest('GET', '/'), $next);

            self::assertSame(503, $response->getStatusCode());
            self::assertSame('Service Unavailable', (string) $response->getBody());
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('60', $response->getHeaderLine('Retry-After'));
            self::assertSame(204, $middleware->process(new ServerRequest('GET', '/health'), $next)->getStatusCode());
            self::assertSame(503, $middleware->process(new ServerRequest('POST', '/health'), $next)->getStatusCode());
        } finally {
            $maintenance->disable();
        }
    }
}
