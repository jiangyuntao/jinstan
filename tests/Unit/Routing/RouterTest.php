<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Routing;

use Jinstan\Core\Infrastructure\Filesystem\Routing\LocalRouteFileLoader;
use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Support\Container;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RouterTest extends TestCase
{
    public function testExistingRegistrationAndDispatchRemainCompatible(): void
    {
        $router = new Router();
        $router->get('/content/{id:\d+}', static fn ($request, array $params): Response => new Response(200, [], $params['id']));

        $response = $router->dispatch(new ServerRequest('GET', '/content/42/'));

        self::assertNotNull($response);
        self::assertSame('42', (string) $response->getBody());
    }

    public function testCompiledDispatcherIsInvalidatedWhenARouteIsAdded(): void
    {
        $router = new Router();
        $router->get('/first', static fn (): Response => new Response(200, [], 'first'));

        self::assertSame('first', (string) $router->dispatch(new ServerRequest('GET', '/first'))?->getBody());

        $router->get('/second', static fn (): Response => new Response(200, [], 'second'));

        self::assertSame('second', (string) $router->dispatch(new ServerRequest('GET', '/second'))?->getBody());
    }

    public function testNamedRouteGeneratesEncodedPathAndQuery(): void
    {
        $router = new Router();
        $router->get('/{locale}/content/{slug}', static fn (): Response => new Response())->name('content.show');

        $url = (new UrlGenerator($router))->route('content.show', [
            'locale' => 'zh-CN',
            'slug' => 'hello world',
            'preview' => 1,
        ]);

        self::assertSame('/zh-CN/content/hello%20world?preview=1', $url);
    }

    public function testDuplicateNamesAndMissingParametersAreRejected(): void
    {
        $router = new Router();
        $router->get('/content/{id}', static fn (): Response => new Response())->name('content.show');

        try {
            $router->get('/articles/{id}', static fn (): Response => new Response())->name('content.show');
            self::fail('Duplicate route name was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Duplicate route name', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        (new UrlGenerator($router))->route('content.show');
    }

    public function testRenamingRouteRemovesPreviousName(): void
    {
        $router = new Router();
        $route = $router->get('/login', static fn (): Response => new Response())->name('login');

        $route->name('login.show');

        self::assertNull($router->named('login'));
        self::assertSame('/login', (new UrlGenerator($router))->route('login.show'));
    }

    public function testNestedPrefixGroupsBuildRoutePath(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/admin'], static function (Router $router): void {
            $router->group(['prefix' => 'users'], static function (Router $router): void {
                $router->get('/{id}', static fn ($request, array $params): Response => new Response(200, [], $params['id']))
                    ->name('admin.users.show');
            });
        });

        self::assertSame('/admin/users/7', (new UrlGenerator($router))->route('admin.users.show', ['id' => 7]));
        self::assertSame('7', (string) $router->dispatch(new ServerRequest('GET', '/admin/users/7'))?->getBody());
    }

    public function testGroupAndRouteMiddlewareRunInOrderAndCanShortCircuit(): void
    {
        $events = [];
        $group = new RoutingMiddleware('group', $events);
        $route = new RoutingMiddleware('route', $events, true);
        $router = new Router();
        $router->group(['middleware' => [$group]], static function (Router $router) use ($route, &$events): void {
            $router->get('/protected', static function () use (&$events): Response {
                $events[] = 'controller';

                return new Response(200);
            })->middleware($route);
        });

        $response = $router->dispatch(new ServerRequest('GET', '/protected'));

        self::assertSame(418, $response?->getStatusCode());
        self::assertSame(['group:before', 'route:before', 'group:after'], $events);
    }

    public function testControllerClassActionIsResolvedFromContainer(): void
    {
        $container = new Container();
        $container->set(RoutingTestController::class, new RoutingTestController());
        $router = new Router($container);
        $router->get('/controller/{id}', [RoutingTestController::class, 'show']);

        $response = $router->dispatch(new ServerRequest('GET', '/controller/42'));

        self::assertSame('controller:42', (string) $response?->getBody());
    }

    public function testRouteRegistrarExposesRegistrationWithoutDispatch(): void
    {
        $router = new Router();
        $registrar = new RouteRegistrar($router);
        $registrar->group(['prefix' => '/plugin'], static function (RouteRegistrar $routes): void {
            $routes->get('/status', static fn (): Response => new Response(200, [], 'ready'))
                ->name('plugin.status');
        });

        self::assertFalse(method_exists($registrar, 'dispatch'));
        self::assertSame('/plugin/status', (new UrlGenerator($router))->route('plugin.status'));
        self::assertSame('ready', (string) $router->dispatch(new ServerRequest('GET', '/plugin/status'))?->getBody());
    }

    public function testRouteRegistrarLoadsAValidatedRouteFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jinstan-routes-');
        self::assertIsString($file);
        $phpFile = $file . '.php';
        rename($file, $phpFile);
        file_put_contents($phpFile, '<?php return static function ($routes): void { $routes->get("/loaded", static fn () => new \\Nyholm\\Psr7\\Response(204)); };');

        try {
            $router = new Router();
            (new RouteRegistrar($router, new LocalRouteFileLoader()))->load($phpFile);

            self::assertSame(204, $router->dispatch(new ServerRequest('GET', '/loaded'))?->getStatusCode());
        } finally {
            unlink($phpFile);
        }
    }
}

final class RoutingTestController
{
    /** @param array<string, string> $parameters */
    public function show(ServerRequestInterface $request, array $parameters): ResponseInterface
    {
        return new Response(200, [], 'controller:' . $parameters['id']);
    }
}

final class RoutingMiddleware implements Middleware
{
    /** @param list<string> $events */
    public function __construct(
        private readonly string $name,
        private array &$events,
        private readonly bool $shortCircuit = false,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        $this->events[] = $this->name . ':before';
        if ($this->shortCircuit) {
            return new Response(418);
        }

        $response = $next->handle($request);
        $this->events[] = $this->name . ':after';

        return $response;
    }
}
