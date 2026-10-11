<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

/**
 * Public API：供前台展示和只读查询使用的最小 Content Repository 契约。
 */
interface ContentReadRepository
{
    public function find(int $id): ?Content;

    /** @return list<Content> */
    public function list(string $type): array;

    public function translation(int $contentId, string $locale): ?ContentTranslation;

    /** @return list<ContentTranslation> */
    public function translations(int $contentId): array;

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     perPage: int
     * }
     */
    public function publishedPage(
        string $type,
        string $locale,
        int $page = 1,
        int $perPage = 12,
        string $sort = 'newest',
    ): array;

    public function findBySlug(string $type, string $locale, string $slug): ?Content;

    /** @return list<array{type: string, slug: string, locale: string}> */
    public function publishedSlugs(): array;
}
