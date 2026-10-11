<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Frontend;

use Jinstan\Core\Http\PublicMediaReader;
use Jinstan\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicMediaController
{
    public function __construct(
        private readonly PublicMediaReader $files,
        private readonly ResponseFactory $responses,
    ) {
    }

    /** @param array<string, string> $params */
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $file = $this->files->read($params);
        if ($file === null) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->responses->text($file['body'], 200, [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
