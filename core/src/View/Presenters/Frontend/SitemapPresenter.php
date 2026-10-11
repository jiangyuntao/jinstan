<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Frontend;

final class SitemapPresenter
{
    /** @param array{baseUrl: string, paths: list<string>} $sitemap */
    public function present(array $sitemap): string
    {
        $urls = '';
        foreach ($sitemap['paths'] as $path) {
            $location = htmlspecialchars($sitemap['baseUrl'] . $path, ENT_XML1, 'UTF-8');
            $urls .= '<url><loc>' . $location . '</loc></url>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . $urls
            . '</urlset>';
    }
}
