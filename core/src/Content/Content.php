<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

final class Content
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?int $parentId,
        public readonly string $status,
        public readonly ?string $template,
        public readonly ?int $authorId,
        public readonly array $data,
    ) {
    }
}
