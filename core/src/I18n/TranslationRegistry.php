<?php

declare(strict_types=1);

namespace Jinstan\Core\I18n;

final class TranslationRegistry implements TranslationRegistrar
{
    /** @var array<string, string> */
    private array $paths = [];

    /** @var array<string, array<string, string>> */
    private array $catalogs = [];

    public function __construct(private readonly TranslationCatalogLoader $loader)
    {
    }

    public function register(string $namespace, string $catalogPath): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $namespace) !== 1) {
            throw new \InvalidArgumentException(sprintf('无效的 Translation Namespace：%s。', $namespace));
        }
        $resolved = $this->loader->directory($catalogPath);
        if (isset($this->paths[$namespace]) && $this->paths[$namespace] !== $resolved) {
            throw new \RuntimeException(sprintf('Translation Namespace 已注册：%s。', $namespace));
        }

        $this->paths[$namespace] = $resolved;
    }

    public function translate(string $key, string $locale, string $fallbackLocale): ?string
    {
        [$namespace, $catalogKey] = $this->parse($key);
        if (!isset($this->paths[$namespace])) {
            return null;
        }

        return $this->catalog($namespace, $locale)[$catalogKey]
            ?? $this->catalog($namespace, $fallbackLocale)[$catalogKey]
            ?? null;
    }

    /** @return array{string, string} */
    private function parse(string $key): array
    {
        $parts = explode('::', $key, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException(sprintf('Plugin 翻译 Key 必须包含命名空间：%s。', $key));
        }

        return [$parts[0], $parts[1]];
    }

    /** @return array<string, string> */
    private function catalog(string $namespace, string $locale): array
    {
        $cacheKey = $namespace . ':' . $locale;
        if (isset($this->catalogs[$cacheKey])) {
            return $this->catalogs[$cacheKey];
        }

        return $this->catalogs[$cacheKey] = $this->loader->load($this->paths[$namespace], $locale);
    }
}
