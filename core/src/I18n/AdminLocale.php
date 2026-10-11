<?php

declare(strict_types=1);

namespace Jinstan\Core\I18n;

final class AdminLocale
{
    private string $locale;

    public function __construct(
        private readonly string $catalogPath,
        private readonly TranslationCatalogLoader $catalogs,
        private readonly string $fallback = 'zh-CN',
    ) {
        $this->locale = $this->fallback;
    }

    public function use(string $locale): void
    {
        $this->locale = $this->catalogs->exists($this->catalogPath, $locale)
                ? $locale
                : $this->fallback;
    }

    public function current(): string
    {
        return $this->locale;
    }
}
