<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Frontend;

use Jinstan\Core\SEO\SchemaRegistry;
use Jinstan\Core\View\ThemeView;

final class PublicContentPresenter
{
    public function __construct(
        private readonly ThemeView $theme,
        private readonly SchemaRegistry $schema,
    ) {
    }

    /** @param array<string, mixed> $homepage */
    public function homepage(array $homepage): string
    {
        if ($homepage['kind'] === 'content') {
            return $this->theme->content(
                $homepage['content'],
                $homepage['translation'],
                $homepage['canonical'],
                [],
                $this->schema,
                'homepage',
            );
        }

        return $this->theme->homepage(
            $homepage['sections'],
            $homepage['locale'],
            $homepage['canonical'],
        );
    }

    /** @param array<string, mixed> $content */
    public function content(array $content): string
    {
        return $this->theme->content(
            $content['content'],
            $content['translation'],
            $content['canonical'],
            $content['links'],
            $this->schema,
        );
    }

    /** @param array<string, mixed> $listing */
    public function listing(array $listing): string
    {
        return $this->theme->listing(
            $listing['type'],
            $listing['result'],
            $listing['locale'],
            $listing['canonical'],
            $listing['previous'],
            $listing['next'],
        );
    }

    /** @param array<string, mixed> $notFound */
    public function notFound(array $notFound): string
    {
        return $this->theme->content(
            $notFound['content'],
            $notFound['translation'],
            $notFound['canonical'],
            $notFound['links'],
            $this->schema,
            'not_found',
        );
    }
}
