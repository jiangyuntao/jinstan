<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Taxonomy\Queries;

use Jinstan\Core\Taxonomy\TaxonomyReadRepository;

final class TaxonomyFormQuery
{
    public function __construct(private readonly TaxonomyReadRepository $taxonomies)
    {
    }

    /** @return array{group: array<string, mixed>|null, values: array<string, mixed>} */
    public function execute(bool $isTerm, string $handle, ?int $id, string $locale): array
    {
        $group = $handle !== '' ? $this->taxonomies->taxonomy($handle) : null;
        if ($isTerm) {
            $values = $id !== null
                ? $this->taxonomies->term($handle, $id, $locale)
                : ['name' => '', 'slug' => ''];
        } else {
            $values = $group ?? ['label' => '', 'handle' => ''];
        }

        return ['group' => $group, 'values' => $values];
    }
}
