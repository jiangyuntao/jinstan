<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\SEO\Queries\SitemapQuery;
use Jinstan\Core\View\Presenters\Frontend\SitemapPresenter;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Tests\Support\InstalledTestProject;
use PHPUnit\Framework\TestCase;

final class SitemapApplicationTest extends TestCase
{
    public function testQueryFiltersLocalesAndBuildsListingAndDetailPaths(): void
    {
        $project = InstalledTestProject::create();
        $settings = new SettingsRepository($project->connection());
        $settings->set('site.url', 'https://example.test');
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en,zh-CN');
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $contents = new ContentRepository($project->connection());
        $service = new ContentService($types, $contents, new TaxonomyRepository($project->connection()));
        $content = $service->create(
            'article',
            new ContentTranslation('en', 'Published', 'published', null, []),
            ['content' => 'Body'],
        );
        $service->setPublished($content->id, true);
        $contents->saveTranslation($content->id, new ContentTranslation('fr', 'French', 'francais', null, []));

        $sitemap = (new SitemapQuery($contents, $types, $settings))->execute();

        self::assertSame('https://example.test', $sitemap['baseUrl']);
        self::assertContains('/blog', $sitemap['paths']);
        self::assertContains('/blog/published', $sitemap['paths']);
        self::assertNotContains('/fr/blog/francais', $sitemap['paths']);
    }

    public function testPresenterEscapesXmlLocations(): void
    {
        $xml = (new SitemapPresenter())->present([
            'baseUrl' => 'https://example.test?site=one&mode=public',
            'paths' => ['/articles/example'],
        ]);

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString(
            '<loc>https://example.test?site=one&amp;mode=public/articles/example</loc>',
            $xml,
        );
    }
}
