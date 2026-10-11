<?php

declare(strict_types=1);

namespace Jinstan\Core\SEO;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;

final class SchemaRegistry implements SchemaRegistrar
{
    /** @var list<callable(Content, ContentTranslation): array<string, mixed>> */
    private array $providers = [];

    public function add(callable $provider): void
    {
        $this->providers[] = $provider;
    }

    public function render(Content $content, ContentTranslation $translation): string
    {
        $items = array_map(
            static fn (callable $provider): array => $provider($content, $translation),
            $this->providers,
        );

        return $items === []
            ? ''
            : '<script type="application/ld+json">'
                . json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . '</script>';
    }
}
