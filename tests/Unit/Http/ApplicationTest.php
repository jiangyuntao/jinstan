<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\Application;
use Jinstan\Core\Http\ExceptionHandler;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\Kernel;
use Jinstan\Core\Http\Middleware\AdminFormFeedbackMiddleware;
use Jinstan\Core\Http\RouterRequestHandler;
use Jinstan\Core\Routing\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ApplicationTest extends TestCase
{
    public function testSuccessfulAdminFormRedirectCarriesFeedback(): void
    {
        $router = new Router();
        $router->post('/admin/settings', static fn (): Response => new Response(
            303,
            ['Location' => '/admin/settings?tab=site#identity'],
        ));
        $application = $this->application($router);

        $response = $application->handle(new ServerRequest('POST', '/admin/settings'));

        self::assertSame(
            '/admin/settings?tab=site&_feedback=saved#identity',
            $response->getHeaderLine('Location'),
        );
    }

    public function testFailedAdminFormResponseIsNotChanged(): void
    {
        $router = new Router();
        $router->post('/admin/settings', static fn (): Response => new Response(422, [], '保存失败。'));
        $application = $this->application($router);

        $response = $application->handle(new ServerRequest('POST', '/admin/settings'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('保存失败。', (string) $response->getBody());
        self::assertSame('', $response->getHeaderLine('Location'));
    }

    public function testNonAdminRedirectDoesNotCarryFeedback(): void
    {
        $router = new Router();
        $router->post('/login', static fn (): Response => new Response(303, ['Location' => '/admin']));
        $application = $this->application($router);

        $response = $application->handle(new ServerRequest('POST', '/login'));

        self::assertSame('/admin', $response->getHeaderLine('Location'));
    }

    private function application(Router $router): Application
    {
        return new Application(new Kernel(
            new RouterRequestHandler($router),
            new ExceptionHandler(new NullLogger(), false, new ResponseFactory()),
            [new AdminFormFeedbackMiddleware()],
        ));
    }
}
