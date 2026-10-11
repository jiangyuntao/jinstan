<?php

declare(strict_types=1);

use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Http\Controllers\Frontend\SitemapController;
use Jinstan\Core\Http\Routing\ContentRoutes;
use Jinstan\Core\Http\Controllers\Frontend\PublicContentController;
use Jinstan\Core\Http\Controllers\Frontend\PublicMediaController;

return static function (
    RouteRegistrar $routes,
    string $section,
    ?ContentTypeRegistry $types = null,
    ?PublicContentController $content = null,
): void {
    if ($section === 'before_auth') {
        $routes->get('/', [PublicContentController::class, 'homepage'])->name('home');
        $routes->get('/{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}', [PublicContentController::class, 'homepage']);
        $routes->get('/{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}/', [PublicContentController::class, 'homepage']);
        $routes->get('/sitemap.xml', [SitemapController::class, 'show'])->name('sitemap');

        return;
    }

    if ($section === 'after_auth') {
        $routes->get('/media/{first:[a-f0-9]{2}}/{second:[a-f0-9]{2}}/{file:[a-f0-9]{32}\.(?:jpg|jpeg|png|webp|pdf)}', [PublicMediaController::class, 'show']);

        return;
    }

    if ($section === 'content') {
        if ($types === null || $content === null) {
            throw new InvalidArgumentException('ContentTypeRegistry and PublicContentController are required for Content routes.');
        }

        ContentRoutes::register($routes, $types, $content);

        return;
    }

    throw new InvalidArgumentException(sprintf('Unknown Web route section: %s', $section));
};
