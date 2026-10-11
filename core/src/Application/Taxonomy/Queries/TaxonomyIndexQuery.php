<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Taxonomy\Queries;

use Jinstan\Core\Taxonomy\TaxonomyReadRepository;

final class TaxonomyIndexQuery
{
    public function __construct(private readonly TaxonomyReadRepository $taxonomies)
    {
    }

    /** @return list<array<string, mixed>> */
    public function execute(): array
    {
        return $this->taxonomies->taxonomies();
    }
}
