<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\SEO\Queries;

use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentUrls;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Settings\SettingsReadRepository;

final class SitemapQuery
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly ContentTypeRegistry $types,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /** @return array{baseUrl: string, paths: list<string>} */
    public function execute(): array
    {
        $rows = $this->contents->publishedSlugs();
        $defaultLocale = $this->settings->get('site.default_locale', 'en') ?? 'en';
        $enabledLocales = [
            $defaultLocale,
            ...preg_split(
                '/[\s,]+/',
                $this->settings->get('site.enabled_locales', $defaultLocale) ?? $defaultLocale,
                -1,
                PREG_SPLIT_NO_EMPTY,
            ) ?: [],
        ];
        $urls = new ContentUrls($this->types, $defaultLocale);
        $paths = [];

        foreach ($this->types->all() as $type) {
            if ($type->listEnabled) {
                $paths[$urls->listing($type->handle, $defaultLocale)] = true;
            }
        }
        foreach ($rows as $row) {
            if (!isset($this->types->all()[$row['type']]) || !in_array($row['locale'], $enabledLocales, true)) {
                continue;
            }

            $paths[$urls->detail($row['type'], $row['slug'], $row['locale'])] = true;
            if ($this->types->get($row['type'])->listEnabled) {
                $paths[$urls->listing($row['type'], $row['locale'])] = true;
            }
        }

        return [
            'baseUrl' => rtrim($this->settings->get('site.url', '') ?? '', '/'),
            'paths' => array_keys($paths),
        ];
    }
}
