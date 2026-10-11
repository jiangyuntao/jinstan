<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

interface ContentAttachmentStore
{
    public function store(string $source, string $originalName, bool $imagesOnly): string;

    public function delete(string $path): void;

    /** @return array{body: string, mime: string}|null */
    public function read(string $path): ?array;
}
