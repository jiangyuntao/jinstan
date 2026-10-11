<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\ExceptionHandler;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\Exceptions\AuthorizationException;
use Jinstan\Core\Http\Exceptions\ValidationException;
use Jinstan\Core\Http\Kernel;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class KernelTest extends TestCase
{
    public function testMiddlewareRunsInRegistrationOrder(): void
    {
        $events = [];
        $destination = new CallbackHandler(static function () use (&$events): ResponseInterface {
            $events[] = 'destination';

            return new Response(200);
        });
        $kernel = new Kernel($destination, new ExceptionHandler(new Logger('test'), false, new ResponseFactory()), [
            new RecordingMiddleware('first', $events),
            new RecordingMiddleware('second', $events),
        ]);

        self::assertSame(200, $kernel->handle(new ServerRequest('GET', '/'))->getStatusCode());
        self::assertSame(['first:before', 'second:before', 'destination', 'second:after', 'first:after'], $events);
    }

    public function testMiddlewareCanReturnWithoutCallingTheDestination(): void
    {
        $destination = new CallbackHandler(static fn (): ResponseInterface => throw new \RuntimeException('Must not run'));
        $middleware = new class () implements Middleware {
            public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
            {
                return new Response(204);
            }
        };

        $response = (new Kernel($destination, new ExceptionHandler(new Logger('test'), false, new ResponseFactory()), [$middleware]))
            ->handle(new ServerRequest('GET', '/'));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testKnownHttpExceptionUsesItsStatusWithoutErrorLog(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $destination = new CallbackHandler(static fn (): ResponseInterface => throw new AuthorizationException());

        $response = (new Kernel($destination, new ExceptionHandler($logger, false, new ResponseFactory())))
            ->handle(new ServerRequest('GET', '/admin'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Forbidden', (string) $response->getBody());
        self::assertFalse($handler->hasErrorRecords());
    }

    public function testUnknownExceptionIsLoggedAndHiddenInProduction(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $destination = new CallbackHandler(static fn (): ResponseInterface => throw new \RuntimeException('database password leaked'));

        $response = (new Kernel($destination, new ExceptionHandler($logger, false, new ResponseFactory())))
            ->handle(new ServerRequest('GET', '/'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', (string) $response->getBody());
        self::assertTrue($handler->hasErrorThatContains('Unhandled HTTP exception.'));
        $record = $handler->getRecords()[0];
        self::assertSame(\RuntimeException::class, $record->context['exception_type']);
        self::assertArrayNotHasKey('exception', $record->context);
        self::assertStringNotContainsString('database password leaked', (string) $record->message);
        self::assertStringNotContainsString(
            'database password leaked',
            json_encode($record->context, JSON_THROW_ON_ERROR),
        );
    }

    public function testJsonValidationExceptionContainsStructuredErrors(): void
    {
        $destination = new CallbackHandler(static fn (): ResponseInterface => throw new ValidationException([
            'title' => ['The title is required.'],
        ]));
        $request = (new ServerRequest('POST', '/api/content'))->withHeader('Accept', 'application/json');

        $response = (new Kernel($destination, new ExceptionHandler(new Logger('test'), false, new ResponseFactory())))->handle($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['message' => 'Validation Failed', 'errors' => ['title' => ['The title is required.']]],
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }
}

final class CallbackHandler implements RequestHandler
{
    /** @param \Closure(ServerRequestInterface): ResponseInterface $callback */
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callback)($request);
    }
}

final class RecordingMiddleware implements Middleware
{
    /** @param list<string> $events */
    public function __construct(
        private readonly string $name,
        private array &$events,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        $this->events[] = $this->name . ':before';
        $response = $next->handle($request);
        $this->events[] = $this->name . ':after';

        return $response;
    }
}
