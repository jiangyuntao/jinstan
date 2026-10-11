<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Queries;

use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentUrls;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\SEO\RedirectReadRepository;
use Jinstan\Core\Settings\SettingsReadRepository;

final class PublicContentQuery
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly SettingsReadRepository $settings,
        private readonly ContentTypeRegistry $types,
        private readonly RedirectReadRepository $redirects,
    ) {
    }

    /** @param array<string, string> $params
     *  @return array<string, mixed>
     */
    public function detail(string $requestPath, array $params): array
    {
        $locale = $this->locale($params);
        if ($locale === null) {
            return ['kind' => 'not_found'];
        }

        $content = $this->contents->findBySlug($params['type'], $locale, $params['slug']);
        if ($content === null && $locale !== ContentLocales::FALLBACK) {
            $content = $this->contents->findBySlug(
                $params['type'],
                ContentLocales::FALLBACK,
                $params['slug'],
            );
        }
        $translation = $content === null ? null : $this->translation($content->id, $locale);
        if ($content === null || $translation === null) {
            $target = $this->redirects->target($requestPath);

            return $target === null ? ['kind' => 'not_found'] : ['kind' => 'redirect', 'target' => $target];
        }

        $links = [];
        foreach ($this->contents->translations($content->id) as $item) {
            $links[$item->locale] = $this->absoluteUrl($content->type, $item->slug, $item->locale);
        }

        return [
            'kind' => 'content',
            'content' => $content,
            'translation' => $translation,
            'canonical' => $this->absoluteUrl($content->type, $translation->slug, $translation->locale),
            'links' => $links,
        ];
    }

    /** @param array<string, string> $params
     *  @param array<string, mixed> $query
     *  @return array<string, mixed>
     */
    public function listing(array $params, array $query): array
    {
        $type = $this->types->get($params['type']);
        if (!$type->listEnabled) {
            return ['kind' => 'not_found'];
        }

        $locale = $this->locale($params);
        if ($locale === null) {
            return ['kind' => 'not_found'];
        }
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($page === false) {
            return ['kind' => 'not_found'];
        }

        $configuredPerPage = $this->settings->get('content.per_page');
        $perPage = $configuredPerPage === null
            ? $type->perPage
            : max(1, min(100, (int) $configuredPerPage));
        $result = $this->contents->publishedPage($type->handle, $locale, $page, $perPage, $this->contentSort());
        if ($result['total'] === 0 && $locale !== ContentLocales::FALLBACK) {
            $result = $this->contents->publishedPage(
                $type->handle,
                ContentLocales::FALLBACK,
                $page,
                $perPage,
                $this->contentSort(),
            );
        }
        if ($page > $result['pages']) {
            return ['kind' => 'not_found'];
        }

        $urls = $this->urls();
        foreach ($result['items'] as &$item) {
            $item['url'] = $urls->detail($type->handle, $item['slug'], $locale);
        }
        unset($item);

        return [
            'kind' => 'listing',
            'type' => $type,
            'result' => $result,
            'locale' => $locale,
            'canonical' => $this->absolute($urls->listing($type->handle, $locale, $page)),
            'previous' => $page > 1 ? $urls->listing($type->handle, $locale, $page - 1) : null,
            'next' => $page < $result['pages'] ? $urls->listing($type->handle, $locale, $page + 1) : null,
        ];
    }

    /** @param array<string, string> $params */
    private function locale(array $params): ?string
    {
        $default = $this->settings->get('site.default_locale', ContentLocales::FALLBACK)
            ?? ContentLocales::FALLBACK;
        $locale = $params['locale'] ?? $default;

        return in_array($locale, ContentLocales::enabled($this->settings), true) ? $locale : null;
    }

    private function translation(int $contentId, string $locale): ?ContentTranslation
    {
        return $this->contents->translation($contentId, $locale)
            ?? ($locale === ContentLocales::FALLBACK
                ? null
                : $this->contents->translation($contentId, ContentLocales::FALLBACK));
    }

    private function contentSort(): string
    {
        $sort = $this->settings->get('content.sort', 'newest') ?? 'newest';

        return in_array($sort, ['newest', 'oldest', 'title_asc', 'title_desc'], true) ? $sort : 'newest';
    }

    private function urls(): ContentUrls
    {
        return new ContentUrls(
            $this->types,
            $this->settings->get('site.default_locale', ContentLocales::FALLBACK) ?? ContentLocales::FALLBACK,
        );
    }

    private function absoluteUrl(string $type, string $slug, string $locale): string
    {
        return $this->absolute($this->urls()->detail($type, $slug, $locale));
    }

    private function absolute(string $path): string
    {
        return rtrim($this->settings->get('site.url', '') ?? '', '/') . $path;
    }
}
