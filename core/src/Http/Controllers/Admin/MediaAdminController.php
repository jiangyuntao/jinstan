<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\Content\ContentAttachmentStore;
use Jinstan\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MediaAdminController
{
    public function __construct(
        private readonly ContentAttachmentStore $storage,
        private readonly ResponseFactory $responses,
    ) {
    }

    /** @param array<string, string> $params */
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $path = ($params['first'] ?? '') . '/' . ($params['second'] ?? '') . '/' . ($params['file'] ?? '');
        $file = $this->storage->read($path);
        if ($file === null) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->responses->text($file['body'], 200, [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
