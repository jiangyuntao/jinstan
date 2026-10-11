<?php

declare(strict_types=1);

namespace Jinstan\Core\Component;

use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentUrls;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\I18n\ContentLocales;

final class LatestContentComponent implements Component
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly ContentTypeRegistry $types,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    public function name(): string
    {
        return 'latest-content';
    }

    public function data(array $parameters, array $context): array
    {
        $typeHandle = trim((string) ($parameters['type'] ?? 'article'));
        $type = $this->types->get($typeHandle);
        if (!$type->listEnabled) {
            throw new \InvalidArgumentException(sprintf('Content Type "%s" 没有启用列表页。', $typeHandle));
        }

        $defaultLocale = $this->settings->get('site.default_locale', 'en') ?? 'en';
        $translation = $context['translation'] ?? null;
        $locale = $translation instanceof ContentTranslation ? $translation->locale : $defaultLocale;
        $limit = max(1, min(24, (int) ($parameters['limit'] ?? 6)));
        $page = $this->contents->publishedPage($typeHandle, $locale, 1, $limit);
        if ($page['items'] === [] && $locale !== ContentLocales::FALLBACK) {
            $page = $this->contents->publishedPage($typeHandle, ContentLocales::FALLBACK, 1, $limit);
        }
        $urls = new ContentUrls($this->types, $defaultLocale);

        foreach ($page['items'] as &$item) {
            $item['url'] = $urls->detail($typeHandle, $item['slug'], $locale);
        }
        unset($item);

        return [
            'heading' => trim((string) ($parameters['heading'] ?? $type->label)),
            'items' => $page['items'],
            'listUrl' => $urls->listing($typeHandle, $locale),
        ];
    }
}
