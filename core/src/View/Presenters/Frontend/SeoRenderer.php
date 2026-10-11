<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Frontend;

use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Settings\SettingsReadRepository;

final class SeoRenderer
{
    public function __construct(private readonly ?SettingsReadRepository $settings = null)
    {
    }

    public function title(ContentTranslation $translation, bool $appendDefault = true, string $pageType = 'content', ?string $contentType = null, int $page = 1): string
    {
        $title = $translation->seoTitle ?: $translation->title;
        if (!$appendDefault) {
            return $title;
        }

        return $this->formatTitle($pageType, $title, $contentType ?? '', $page);
    }

    public function tags(ContentTranslation $translation, string $canonical, bool $appendDefaultTitle = true, string $pageType = 'content', ?string $contentType = null, int $page = 1): string
    {
        $description = htmlspecialchars($translation->metaDescription ?: $translation->excerpt ?: $this->setting('seo.default_description') ?: $this->setting('site.description'), ENT_QUOTES, 'UTF-8');
        $canonical = htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8');
        $robots = htmlspecialchars($this->setting('seo.robots', 'index,follow'), ENT_QUOTES, 'UTF-8');

        return '<link rel="canonical" href="' . $canonical . '"><meta name="description" content="' . $description . '"><meta name="robots" content="' . $robots . '">';
    }

    public function siteTitle(): string
    {
        return $this->formatTitle('homepage', '', '', 1);
    }

    public function siteTags(string $canonical): string
    {
        return $this->tags(
            new ContentTranslation(
                $this->setting('site.default_locale', 'en'),
                '',
                '',
                null,
                [],
                $this->siteTitle(),
                $this->setting('seo.default_description') ?: $this->setting('site.description'),
            ),
            $canonical,
            false,
        );
    }

    private function setting(string $key, string $default = ''): string
    {
        return $this->settings?->get($key, $default) ?? $default;
    }

    private function formatTitle(string $pageType, string $title, string $contentType, int $page): string
    {
        $defaults = [
            'homepage' => '{site_name}',
            'content' => '{title}{separator}{site_name}',
            'listing' => '{title}{page_suffix}{separator}{site_name}',
            'not_found' => '{title}{separator}{site_name}',
        ];
        $pageType = array_key_exists($pageType, $defaults) ? $pageType : 'content';
        $siteName = $this->setting('seo.default_title') ?: $this->setting('site.name', 'Jinstan');
        $template = $this->setting('seo.title_' . $pageType, $defaults[$pageType]);

        return trim(strtr($template, [
            '{site_name}' => $siteName,
            '{tagline}' => $this->setting('site.tagline'),
            '{title}' => $title,
            '{content_type}' => $contentType,
            '{page}' => (string) max(1, $page),
            '{page_suffix}' => $page > 1 ? ' · ' . $page : '',
            '{separator}' => $this->setting('seo.title_separator', ' | '),
        ]));
    }
}
