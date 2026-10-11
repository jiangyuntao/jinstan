<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Taxonomy\Queries;

use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;

final class TaxonomyTranslationResolver
{
    public function __construct(
        private readonly TaxonomyReadRepository $taxonomies,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /** @return array{locale:string,name:string,slug:string}|null */
    public function source(int $termId, string $targetLocale): ?array
    {
        $translations = $this->taxonomies->termTranslations($termId);
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
