<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Settings\SettingsReadRepository;

final class ContentListPresenter
{
    private const AVAILABLE_COLUMNS = ['id', 'title', 'slug', 'status'];

    private const REQUIRED_COLUMNS = ['id', 'title'];

    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly CsrfToken $csrf,
        private readonly SettingsReadRepository $settings,
        private readonly UrlGenerator $urls,
    ) {
    }

    /** @param array<string, mixed> $queryParameters
     *  @return list<string>
     */
    public function visibleColumns(array $queryParameters): array
    {
        $requested = ($queryParameters['reset_columns'] ?? null) === '1'
            ? self::AVAILABLE_COLUMNS
            : ($queryParameters['columns'] ?? self::AVAILABLE_COLUMNS);

        if (!is_array($requested)) {
            return self::AVAILABLE_COLUMNS;
        }

        $selected = array_values(array_filter($requested, 'is_string'));

        return array_values(array_unique([...self::REQUIRED_COLUMNS, ...array_intersect(self::AVAILABLE_COLUMNS, $selected)]));
    }

    /**
     * @param array{items: list<array{id: int, title: string, slug: string, status: string, translated: bool, sourceLocale: ?string}>, total: int, page: int, pages: int, offset: int} $result
     * @param list<string> $visibleColumns
     * @return array<string, mixed>
     */
    public function present(string $type, string $typeLabel, string $locale, string $query, string $status, array $visibleColumns, array $result, int $perPage = 10): array
    {
        $items = [];
        $localeNames = ContentLocales::options($this->settings);
        foreach ($result['items'] as $item) {
            $items[] = $item + [
                'sourceLocaleName' => $item['sourceLocale'] === null ? null : ($localeNames[$item['sourceLocale']] ?? ContentLocales::catalog()[$item['sourceLocale']]['name'] ?? $item['sourceLocale']),
                'editUrl' => $this->urls->route('admin.content.edit', ['type' => $type, 'id' => $item['id'], 'locale' => $locale]),
                'deleteUrl' => $this->urls->route('admin.content.delete', ['type' => $type, 'id' => $item['id']]),
            ];
        }

        return [
            'typeLabel' => $typeLabel,
            'typeHandle' => $type,
            'items' => $items,
            'filters' => ['q' => $query, 'status' => $status],
            'columns' => array_merge(
                array_values(array_filter([
                    ['label' => $this->views->translate('ID'), 'name' => 'id', 'view' => 'metadata.jst'],
                    ['label' => $this->views->translate('标题'), 'name' => 'title', 'url' => 'editUrl', 'view' => 'translation-link.jst'],
                    ['label' => $this->views->translate('content.slug'), 'name' => 'slug', 'view' => 'metadata.jst'],
                    ['label' => $this->views->translate('状态'), 'name' => 'status', 'view' => 'badge.jst'],
                ], static fn (array $column): bool => in_array($column['name'], $visibleColumns, true))),
                [['label' => $this->views->translate('操作'), 'view' => 'actions.jst', 'csrfToken' => $this->csrf->value()]],
            ),
            'visibleColumns' => $visibleColumns,
            'requiredColumns' => self::REQUIRED_COLUMNS,
            'locale' => $locale,
            'locales' => $localeNames,
            'pagination' => $this->pagination($type, $result, $query, $status, $visibleColumns, $locale, $perPage),
        ];
    }

    /** @param array{total: int, page: int, pages: int, offset: int} $result
     *  @param list<string> $visibleColumns
     *  @return array{current: int, total: int, from: int, to: int, previousUrl: ?string, nextUrl: ?string, pages: list<array{number: int, url: string, current: bool}>}
     */
    private function pagination(string $type, array $result, string $query, string $status, array $visibleColumns, string $locale, int $perPage): array
    {
        $pageUrl = fn (int $number): string => $this->urls->route('admin.content.index', [
            'type' => $type,
            'q' => $query,
            'status' => $status,
            'columns' => $visibleColumns,
            'locale' => $locale,
            'page' => $number,
        ]);
        $pages = [];
        foreach (range(1, $result['pages']) as $number) {
            $pages[] = ['number' => $number, 'url' => $pageUrl($number), 'current' => $number === $result['page']];
        }

        return [
            'current' => $result['page'],
            'total' => $result['total'],
            'from' => $result['total'] === 0 ? 0 : $result['offset'] + 1,
            'to' => min($result['offset'] + $perPage, $result['total']),
            'previousUrl' => $result['page'] > 1 ? $pageUrl($result['page'] - 1) : null,
            'nextUrl' => $result['page'] < $result['pages'] ? $pageUrl($result['page'] + 1) : null,
            'pages' => $pages,
        ];
    }
}
