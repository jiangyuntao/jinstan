<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

use Jinstan\Core\Http\Exceptions\HttpException;
use Jinstan\Core\Http\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ExceptionHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function render(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        if ($exception instanceof HttpException) {
            if ($this->expectsJson($request)) {
                $payload = ['message' => $exception->getMessage()];
                if ($exception instanceof ValidationException) {
                    $payload['errors'] = $exception->errors();
                }

                return $this->responses->json($payload, $exception->statusCode());
            }

            return $this->responses->text($exception->getMessage(), $exception->statusCode());
        }

        $this->logger->error('Unhandled HTTP exception.', [
            // 异常消息和 Trace 可能包含密码、Token、DSN 或请求数据，不进入日志上下文。
            'exception_type' => $exception::class,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ]);

        $message = $this->debug ? $exception->getMessage() : 'Internal Server Error';

        return $this->expectsJson($request)
            ? $this->responses->json(['message' => $message], 500)
            : $this->responses->text($message, 500);
    }

    private function expectsJson(ServerRequestInterface $request): bool
    {
        return str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json');
    }
}
