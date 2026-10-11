<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Content;

use Jinstan\Core\Http\PublicMediaReader;
use Jinstan\Core\Content\ContentAttachmentPath;
use Jinstan\Core\Content\ContentAttachmentStore;
use Jinstan\Core\Content\PublicMediaAccessPolicy;

final class PublicMediaFileReader implements PublicMediaReader
{
    public function __construct(
        private readonly ContentAttachmentStore $storage,
        private readonly PublicMediaAccessPolicy $access,
    ) {
    }

    /** @param array<string, string> $routeParams
     *  @return array{body: string, mime: string}|null
     */
    public function read(array $routeParams): ?array
    {
        $path = ($routeParams['first'] ?? '')
            . '/'
            . ($routeParams['second'] ?? '')
            . '/'
            . ($routeParams['file'] ?? '');

        if (!ContentAttachmentPath::isValid($path) || !$this->access->allows($path)) {
            return null;
        }

        return $this->storage->read($path);
    }
}
