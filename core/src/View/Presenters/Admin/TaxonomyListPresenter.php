<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Settings\SettingsReadRepository;

final class TaxonomyListPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly SettingsReadRepository $settings,
        private readonly UrlGenerator $urls,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $taxonomies
     */
    public function index(array $taxonomies): AdminPageView
    {
        return new AdminPageView(
            'taxonomies',
            $this->views->translate('分类法'),
            ['taxonomies' => $taxonomies],
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function terms(string $handle, array $query): AdminPageView
    {
        foreach ($query['result']['items'] as &$item) {
            $item['name'] ??= $this->views->translate('content.untranslated');
        }
        unset($item);

        $search = $query['search'];
        $locale = $query['locale'];
        $result = $query['result'];
        $context = ['locale' => $locale, 'q' => $search, 'page' => $result['page']];

        return new AdminPageView(
            'taxonomy-terms',
            $query['group']['label'],
            [
                'selected' => $handle,
                'selectedLabel' => $query['group']['label'],
                'query' => $search,
                'result' => $result,
                'context' => http_build_query($context),
                'previousUrl' => $this->listUrl($handle, [...$context, 'page' => $result['page'] - 1]),
                'nextUrl' => $this->listUrl($handle, [...$context, 'page' => $result['page'] + 1]),
                'locale' => $locale,
                'locales' => ContentLocales::options($this->settings),
            ],
        );
    }

    /** @param array<string, mixed> $context */
    private function listUrl(string $handle, array $context): string
    {
        return $this->urls->route('admin.taxonomies.index', [
            'taxonomy' => $handle,
            'locale' => ContentLocales::requested($context, $this->settings),
            'q' => mb_substr($this->text($context, 'q'), 0, 100),
            'page' => max(1, (int) (filter_var($context['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1)),
        ]);
    }

    /** @param array<string, mixed> $input */
    private function text(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }
}
