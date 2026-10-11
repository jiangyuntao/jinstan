<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Queries;

use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentUrls;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Settings\SettingsReadRepository;

final class PublicNotFoundQuery
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
    public function execute(string $requestPath, array $params): array
    {
        $locale = $this->locale($requestPath, $params);
        $pageId = (int) ($this->settings->get('site.not_found_page_id', '0') ?? 0);
        $content = $pageId > 0 ? $this->contents->find($pageId) : null;
        $translation = $content === null ? null : $this->translation($content->id, $locale);

        if ($content === null
            || $content->type !== 'page'
            || $content->status !== 'published'
            || $translation === null) {
            return ['kind' => 'plain'];
        }

        $links = [];
        foreach ($this->contents->translations($content->id) as $item) {
            $links[$item->locale] = $this->absoluteUrl($content->type, $item->slug, $item->locale);
        }

        return [
            'kind' => 'content',
            'content' => $content,
            'translation' => $translation,
            'canonical' => $this->absolute($requestPath),
            'links' => $links,
        ];
    }

    /** @param array<string, string> $params */
    private function locale(string $requestPath, array $params): string
    {
        $enabled = ContentLocales::enabled($this->settings);
        $requested = $params['locale'] ?? explode('/', trim($requestPath, '/'))[0] ?? '';
        if (is_string($requested) && in_array($requested, $enabled, true)) {
            return $requested;
        }

        return $this->settings->get('site.default_locale', ContentLocales::FALLBACK)
            ?? ContentLocales::FALLBACK;
    }

    private function translation(int $contentId, string $locale): ?ContentTranslation
    {
        return $this->contents->translation($contentId, $locale)
            ?? ($locale === ContentLocales::FALLBACK
                ? null
                : $this->contents->translation($contentId, ContentLocales::FALLBACK));
    }

    private function absoluteUrl(string $type, string $slug, string $locale): string
    {
        $urls = new ContentUrls(
            $this->types,
            $this->settings->get('site.default_locale', ContentLocales::FALLBACK) ?? ContentLocales::FALLBACK,
        );

        return $this->absolute($urls->detail($type, $slug, $locale));
    }

    private function absolute(string $path): string
    {
        return rtrim($this->settings->get('site.url', '') ?? '', '/') . $path;
    }
}
