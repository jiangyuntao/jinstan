<?php

declare(strict_types=1);

namespace Jinstan\Core\View;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\SEO\SchemaRegistry;

interface ThemeView
{
    /** @param array<string, string> $hreflang */
    public function content(
        Content $content,
        ContentTranslation $translation,
        string $canonical = '/',
        array $hreflang = [],
        ?SchemaRegistry $schema = null,
        string $pageType = 'content',
    ): string;

    /** @param array<string, mixed> $pagination */
    public function listing(
        ContentTypeDefinition $type,
        array $pagination,
        string $locale,
        string $canonical,
        ?string $previousUrl,
        ?string $nextUrl,
    ): string;

    /** @param list<array{type: ContentTypeDefinition, items: array, url: string}> $sections */
    public function homepage(array $sections, string $locale, string $canonical): string;
}
