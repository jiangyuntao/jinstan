<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Queries;

use Jinstan\Core\Content\ContentAdminReadRepository;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;

final class ContentSearchQuery
{
    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly ContentAdminReadRepository $contents,
        private readonly TaxonomyReadRepository $taxonomy,
    ) {
    }

    /** @return list<array{id: int, title: string}> */
    public function parents(string $type, string $locale, string $query, ?int $excludeId = null): array
    {
        $definition = $this->types->get($type);
        if (!$definition->hierarchical) {
            throw new \InvalidArgumentException('此内容类型不支持上级内容。');
        }
        $query = trim($query);

        return $query === '' ? [] : $this->contents->searchTitles($type, $locale, $query, $excludeId);
    }

    /** @return list<array{id: int, name: string}> */
    public function terms(string $type, string $group, string $locale, ?string $query): array
    {
        if (!isset($this->types->get($type)->taxonomies[$group])) {
            throw new \InvalidArgumentException('此内容类型未启用该分类分组。');
        }

        return $query === null ? [] : $this->taxonomy->search($group, mb_substr($query, 0, 100), $locale);
    }
}
