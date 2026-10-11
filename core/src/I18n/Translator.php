<?php

declare(strict_types=1);

namespace Jinstan\Core\I18n;

/**
 * Loads small PHP translation catalogs without exposing a template engine to
 * language packs. Catalogs use stable keys and may fall back to a base locale.
 */
final class Translator
{
    /** @var array<string, array<string, string>> */
    private array $catalogs = [];

    /** @param \Closure(): string $locale */
    public function __construct(
        private readonly string $catalogPath,
        private readonly \Closure $locale,
        private readonly TranslationCatalogLoader $loader,
        private readonly string $fallbackLocale = 'zh-CN',
        private readonly ?TranslationRegistry $extensions = null,
    ) {
    }

    /** @param array<string, string|int|float> $parameters */
    public function translate(string $key, array $parameters = []): string
    {
        $locale = $this->locale();
        $translation = str_contains($key, '::')
            ? ($this->extensions?->translate($key, $locale, $this->fallbackLocale) ?? $key)
            : ($this->catalog($locale)[$key] ?? $this->catalog($this->fallbackLocale)[$key] ?? $key);

        foreach ($parameters as $name => $value) {
            $translation = str_replace('{' . $name . '}', (string) $value, $translation);
        }

        return $translation;
    }

    public function locale(): string
    {
        return ($this->locale)();
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        if (isset($this->catalogs[$locale])) {
            return $this->catalogs[$locale];
        }

        return $this->catalogs[$locale] = $this->loader->load($this->catalogPath, $locale);
    }
}
