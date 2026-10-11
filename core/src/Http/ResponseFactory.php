<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class ResponseFactory
{
    public function __construct(private readonly ?ResponseFileReader $files = null)
    {
    }

    /** @param array<string, string|string[]> $headers */
    public function text(string $content = '', int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers, $content);
    }

    /** @param array<string, string|string[]> $headers */
    public function html(string $content, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->text($content, $status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    /** @param array<string, mixed> $data */
    public function json(array $data, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->text(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers,
        );
    }

    /** @param array<string, string|string[]> $headers */
    public function redirect(string $location, int $status = 303, array $headers = []): ResponseInterface
    {
        return $this->text('', $status, ['Location' => $location] + $headers);
    }

    /** @param array<string, string|string[]> $headers */
    public function file(string $path, string $contentType = 'application/octet-stream', array $headers = []): ResponseInterface
    {
        $files = $this->files ?? throw new \LogicException('Response file reader 未配置。');
        $content = $files->read($path);

        return $this->text($content, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($content),
        ] + $headers);
    }
}
