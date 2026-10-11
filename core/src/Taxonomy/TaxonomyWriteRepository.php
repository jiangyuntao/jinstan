<?php

declare(strict_types=1);

namespace Jinstan\Core\Taxonomy;

use Jinstan\Core\Content\ContentTypeRegistry;

/** Public API：Taxonomy、Term 与 Content 分类关系的写入契约。 */
interface TaxonomyWriteRepository
{
    public function createTaxonomy(string $handle, string $label): void;

    public function updateTaxonomy(string $handle, string $label): void;

    public function deleteTaxonomy(string $handle, ContentTypeRegistry $types): void;

    public function createTerm(
        string $taxonomy,
        string $locale,
        string $name,
        string $slug,
        ?int $parentId = null,
    ): int;

    public function updateTerm(string $handle, int $id, string $locale, string $name, string $slug): void;

    public function deleteTerm(string $handle, int $id): void;

    /** @param list<int> $termIds */
    public function reorder(string $taxonomy, array $termIds, int $page): void;

    /** @param array<string, list<int>> $selections */
    public function sync(int $contentId, array $selections): void;

    public function transaction(callable $operation): mixed;
}
