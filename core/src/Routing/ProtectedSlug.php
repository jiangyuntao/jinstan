<?php

declare(strict_types=1);

namespace Jinstan\Core\Routing;

use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Settings\SettingsReadRepository;

final class ProtectedSlug
{
    public const DEFAULT_ROUTES = [
        '/admin',
        '/install',
        '/login',
        '/logout',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/search',
        '/category',
        '/tag',
        '/author',
        '/archive',
        '/archives',
        '/sitemap.xml',
        '/sitemap-index.xml',
        '/robots.txt',
        '/rss',
        '/feed',
        '/atom.xml',
        '/api',
        '/graphql',
        '/cron',
        '/health',
        '/status',
        '/media',
        '/uploads',
        '/assets',
    ];

    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    public function assertContentPathAvailable(string $type, string $slug): void
    {
        $path = trim(str_replace('{slug}', $slug, $this->types->get($type)->urlPattern), '/');
        if (in_array(mb_strtolower($path), $this->paths(), true)) {
            throw new \InvalidArgumentException('此 URL 路径受系统保护，请更换 URL 别名。');
        }
    }

    /** @return list<string> */
    public function paths(): array
    {
        $paths = array_map(static fn (string $route): string => trim($route, '/'), self::DEFAULT_ROUTES);
        foreach ($this->types->all() as $type) {
            if ($type->listEnabled && $type->listPath !== '') {
                $paths[] = trim($type->listPath, '/');
            }
            $literal = trim(str_replace('/{slug}', '', $type->urlPattern), '/');
            if ($literal !== '' && !str_contains($literal, '{slug}')) {
                $paths[] = $literal;
            }
        }
        $custom = preg_split('/[\s,]+/', $this->settings->get('routing.protected_slugs', '') ?? '', -1, PREG_SPLIT_NO_EMPTY);
        foreach ($custom ?: [] as $path) {
            $normalized = mb_strtolower(trim($path, '/'));
            if ($normalized !== '') {
                $paths[] = $normalized;
            }
        }

        return array_values(array_unique(array_map('mb_strtolower', $paths)));
    }
}
