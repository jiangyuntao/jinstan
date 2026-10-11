<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\I18n;

use Jinstan\Core\I18n\TranslationCatalogLoader;

final class PhpTranslationCatalogLoader implements TranslationCatalogLoader
{
    public function directory(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException(sprintf('语言包目录不存在：%s。', $path));
        }

        return $resolved;
    }

    public function exists(string $directory, string $locale): bool
    {
        return $this->validLocale($locale) && is_file($directory . '/' . $locale . '.php');
    }

    public function load(string $directory, string $locale): array
    {
        if (!$this->exists($directory, $locale)) {
            return [];
        }

        $path = $directory . '/' . $locale . '.php';
        $catalog = require $path;
        if (!is_array($catalog)) {
            throw new \RuntimeException(sprintf('语言包必须返回数组：%s', $path));
        }

        return array_filter($catalog, 'is_string');
    }

    private function validLocale(string $locale): bool
    {
        return preg_match('/^[A-Za-z0-9-]+$/D', $locale) === 1;
    }
}
