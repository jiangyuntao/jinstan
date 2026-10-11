<?php

declare(strict_types=1);

namespace Jinstan\Core\I18n;

interface TranslationCatalogLoader
{
    public function directory(string $path): string;

    public function exists(string $directory, string $locale): bool;

    /** @return array<string, string> */
    public function load(string $directory, string $locale): array;
}
