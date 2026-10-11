<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

final class ContentTranslation
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $locale,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $excerpt,
        public readonly array $data,
        public readonly ?string $seoTitle = null,
        public readonly ?string $metaDescription = null,
    ) {
    }
}
