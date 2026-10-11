<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Navigation\Queries;

use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Navigation\NavigationReadRepository;
use Jinstan\Core\Settings\SettingsReadRepository;

final class NavigationTranslationResolver
{
    public function __construct(
        private readonly NavigationReadRepository $navigation,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /** @return array{locale: string, label: string, url: string}|null */
    public function source(int $itemId, string $targetLocale): ?array
    {
        $translations = $this->navigation->translations($itemId);
        $preferred = [
            $this->settings->get('site.default_locale', ContentLocales::FALLBACK),
            ContentLocales::FALLBACK,
        ];
        foreach ([...$preferred, ...array_column($translations, 'locale')] as $locale) {
            foreach ($translations as $translation) {
                if ($locale !== $targetLocale && $translation['locale'] === $locale) {
                    return $translation;
                }
            }
        }

        return null;
    }

    public function localeName(string $locale): string
    {
        return ContentLocales::options($this->settings)[$locale]
            ?? ContentLocales::catalog()[$locale]['name']
            ?? $locale;
    }
}
