<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Queries;

use Jinstan\Core\Content\ContentAdminReadRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Settings\SettingsReadRepository;

final class ContentListQuery
{
    public function __construct(
        private readonly ContentAdminReadRepository $contents,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /**
     * @param list<string> $columns
     * @return array{items: list<array{id: int, title: string, slug: string, status: string, translated: bool, sourceLocale: ?string}>, total: int, page: int, pages: int, offset: int}
     */
    public function list(string $type, string $locale, string $query, string $status, int $page, array $columns, string $untranslatedLabel, int $perPage = 10): array
    {
        $perPage = max(1, $perPage);
        $databasePage = $query === '' ? $this->contents->adminPage($type, $status, $page, $perPage) : null;
        $items = [];
        foreach ($databasePage['items'] ?? $this->contents->list($type) as $content) {
            $translation = $this->contents->translation($content->id, $locale);
            $source = $translation === null ? $this->sourceTranslation($content->id, $locale) : null;
            $title = $translation?->title ?? $source?->title ?? $untranslatedLabel;
            $slug = $translation?->slug ?? $source?->slug ?? '';
            $searchable = [];
            foreach (['id' => (string) $content->id, 'title' => $title, 'slug' => $slug, 'status' => $content->status] as $column => $value) {
                if (in_array($column, $columns, true)) {
                    $searchable[] = $value;
                }
            }
            if ($query !== '' && !str_contains(mb_strtolower(implode(' ', $searchable)), mb_strtolower($query))) {
                continue;
            }
            if ($status !== '' && $content->status !== $status) {
                continue;
            }
            $items[] = [
                'id' => $content->id,
                'title' => $title,
                'slug' => $slug,
                'status' => $content->status,
                'translated' => $translation !== null,
                'sourceLocale' => $source?->locale,
            ];
        }

        $total = $databasePage['total'] ?? count($items);
        $pages = $databasePage['pages'] ?? max(1, (int) ceil($total / $perPage));
        $page = $databasePage['page'] ?? min(max(1, $page), $pages);
        $offset = $databasePage['offset'] ?? ($page - 1) * $perPage;

        return [
            'items' => $databasePage === null ? array_slice($items, $offset, $perPage) : $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'offset' => $offset,
        ];
    }

    private function sourceTranslation(int $contentId, string $targetLocale): ?ContentTranslation
    {
        $translations = $this->contents->translations($contentId);
        $preferred = [
            $this->settings->get('site.default_locale', 'zh-CN') ?? 'zh-CN',
            'zh-CN',
        ];
        foreach ($preferred as $locale) {
            if ($locale === $targetLocale) {
                continue;
            }
            foreach ($translations as $translation) {
                if ($translation->locale === $locale) {
                    return $translation;
                }
            }
        }
        foreach ($translations as $translation) {
            if ($translation->locale !== $targetLocale) {
                return $translation;
            }
        }

        return null;
    }
}
