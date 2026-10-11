<?php

declare(strict_types=1);

namespace Jinstan\Core\Component;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentRelationReadRepository;

final class RelatedPostsComponent implements Component
{
    public function __construct(
        private readonly ContentReadRepository&ContentRelationReadRepository $contents,
    ) {
    }

    public function name(): string
    {
        return 'related-posts';
    }

    public function data(array $parameters, array $context): array
    {
        $content = $context['content'] ?? null;
        if (!$content instanceof Content) {
            return ['items' => []];
        }

        $limit = max(1, (int) ($parameters['limit'] ?? 6));
        $items = [];
        foreach (array_slice($this->contents->related($content->id, 'related'), 0, $limit) as $related) {
            $translation = $this->contents->translation($related->id, 'en');
            if ($translation !== null) {
                $items[] = ['title' => $translation->title, 'slug' => $translation->slug, 'type' => $related->type];
            }
        }

        return ['items' => $items];
    }
}
