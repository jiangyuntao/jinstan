<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

/** Public API：内容类型配置对应的前台 URL。 */
final class ContentUrls
{
    public function __construct(private readonly ContentTypeRegistry $types, private readonly string $defaultLocale = 'en')
    {
    }

    public function detail(string $type, string $slug, string $locale): string
    {
        return $this->prefix($locale) . str_replace('{slug}', rawurlencode($slug), $this->types->get($type)->urlPattern);
    }

    public function listing(string $type, string $locale, int $page = 1): string
    {
        return $this->prefix($locale) . $this->types->get($type)->listPath . ($page > 1 ? '?page=' . $page : '');
    }

    private function prefix(string $locale): string
    {
        return $locale === $this->defaultLocale ? '' : '/' . rawurlencode($locale);
    }

    public static function validatePath(string $path, bool $detail): void
    {
        $literal = $detail ? str_replace('{slug}', 'slug', $path) : $path;
        if (($detail && substr_count($path, '{slug}') !== 1) || !preg_match('~^/(?:[a-zA-Z0-9_.-]+/)*[a-zA-Z0-9_.-]+$~D', $literal)) {
            throw new \InvalidArgumentException($detail ? '详情 URL 必须是以 / 开头的路径，且包含一个 {slug}。' : '列表路径必须以 / 开头，只能包含字母、数字、连字符、下划线和路径分隔符。');
        }
        $segments = explode('/', ltrim($literal, '/'));
        if (array_intersect($segments, ['.', '..']) !== []) {
            throw new \InvalidArgumentException('路径不能包含相对目录。');
        }
        $first = $segments[0];
        if (in_array($first, ['admin', 'install', 'login', 'logout', 'assets', 'storage', 'api', 'sitemap.xml', 'robots.txt', 'favicon.ico'], true)) {
            throw new \InvalidArgumentException('此路径与系统保留路径冲突。');
        }
    }

    public static function validateTypes(ContentTypeRegistry $types): void
    {
        $paths = [];
        foreach ($types->all() as $type) {
            self::validatePath($type->urlPattern, true);
            $current = [$type->urlPattern];
            if ($type->listEnabled) {
                self::validatePath($type->listPath, false);
                $current[] = $type->listPath;
            }
            foreach ($current as $path) {
                if (isset($paths[$path])) {
                    throw new \InvalidArgumentException('前台路径重复：' . $path);
                }
                $paths[$path] = true;
            }
        }
    }
}
