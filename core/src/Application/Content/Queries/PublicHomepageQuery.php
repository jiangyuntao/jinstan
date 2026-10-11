<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Queries;

use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentUrls;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Settings\SettingsReadRepository;

final class PublicHomepageQuery
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly SettingsReadRepository $settings,
        private readonly ContentTypeRegistry $types,
    ) {
    }

    /** @param array<string, string> $params
     *  @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $locale = $this->locale($params);
        if ($locale === null) {
            return ['kind' => 'not_found'];
        }

        $homepageId = (int) ($this->settings->get('site.homepage_id', '0') ?? 0);
        $content = $homepageId > 0 ? $this->contents->find($homepageId) : null;
        $translation = $content === null ? null : $this->translation($content->id, $locale);
        $canonical = $this->absolute($this->localePrefix($params) . '/');

        if ($content !== null
            && $content->type === 'page'
            && $content->status === 'published'
            && $translation !== null) {
            return [
                'kind' => 'content',
                'content' => $content,
                'translation' => $translation,
                'canonical' => $canonical,
            ];
        }

        $urls = new ContentUrls(
            $this->types,
            $this->settings->get('site.default_locale', ContentLocales::FALLBACK) ?? ContentLocales::FALLBACK,
        );
        $sections = [];
        foreach ($this->types->all() as $type) {
            if (!$type->listEnabled) {
                continue;
            }

            $items = $this->publishedItems($type->handle, $locale);
            foreach ($items as &$item) {
                $item['url'] = $urls->detail($type->handle, $item['slug'], $locale);
            }
            unset($item);
            $sections[] = [
                'type' => $type,
                'items' => $items,
                'url' => $urls->listing($type->handle, $locale),
            ];
        }

        return [
            'kind' => 'homepage',
            'sections' => $sections,
            'locale' => $locale,
            'canonical' => $canonical,
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

    /** @return list<array<string, mixed>> */
    private function publishedItems(string $type, string $locale): array
    {
        $result = $this->contents->publishedPage($type, $locale, 1, 6, $this->contentSort());
        if ($result['items'] === [] && $locale !== ContentLocales::FALLBACK) {
            return $this->contents->publishedPage(
                $type,
                ContentLocales::FALLBACK,
                1,
                6,
                $this->contentSort(),
            )['items'];
        }

        return $result['items'];
    }

    private function contentSort(): string
    {
        $sort = $this->settings->get('content.sort', 'newest') ?? 'newest';

        return in_array($sort, ['newest', 'oldest', 'title_asc', 'title_desc'], true) ? $sort : 'newest';
    }

    /** @param array<string, string> $params */
    private function localePrefix(array $params): string
    {
        return isset($params['locale']) ? '/' . rawurlencode($params['locale']) : '';
    }

    private function absolute(string $path): string
    {
        return rtrim($this->settings->get('site.url', '') ?? '', '/') . $path;
    }
}
