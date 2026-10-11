<?php

declare(strict_types=1);

namespace Jinstan\Core\Taxonomy;

/** Public API：Taxonomy 与 Term 的只读查询契约。 */
interface TaxonomyReadRepository
{
    /** @return array<string, mixed> */
    public function taxonomy(string $handle): array;

    /** @return array<string, mixed> */
    public function term(string $handle, int $id, string $locale = 'en'): array;

    /** @return list<array<string, mixed>> */
    public function termTranslations(int $id): array;

    /** @return list<array{handle: string, label: string}> */
    public function taxonomies(): array;

    /** @return list<array<string, mixed>> */
    public function terms(string $locale = 'en'): array;

    /** @return array{items: array, total: int, page: int, pages: int} */
    public function termPage(string $taxonomy, string $query = '', int $page = 1, string $locale = 'en'): array;

    /** @return list<array<string, mixed>> */
    public function selected(int $contentId, string $locale = 'en'): array;

    /** @return list<array<string, mixed>> */
    public function search(string $group, string $query, string $locale = 'en'): array;

    /** @return array<string, list<int>> */
    public function validateSelections(array $bindings, array $input): array;
}
