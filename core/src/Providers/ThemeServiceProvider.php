<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Application\Content\TemplateCatalogReader;
use Jinstan\Core\Component\BreadcrumbComponent;
use Jinstan\Core\Component\ComponentRegistry;
use Jinstan\Core\Component\LatestContentComponent;
use Jinstan\Core\Component\NavigationMenuComponent;
use Jinstan\Core\Component\RelatedPostsComponent;
use Jinstan\Core\Content\ContentRepositoryContract;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Event\EventDispatcher;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\PublicMediaReader;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Navigation\NavigationReadRepository;
use Jinstan\Core\Navigation\NavigationRepositoryContract;
use Jinstan\Core\Navigation\NavigationWriteRepository;
use Jinstan\Core\Routing\ProtectedSlug;
use Jinstan\Core\Infrastructure\Persistence\SEO\RedirectRepository;
use Jinstan\Core\SEO\RedirectReadRepository;
use Jinstan\Core\SEO\RedirectRepositoryContract;
use Jinstan\Core\SEO\RedirectWriteRepository;
use Jinstan\Core\SEO\SchemaRegistry;
use Jinstan\Core\SEO\SchemaRegistrar;
use Jinstan\Core\Http\Controllers\Frontend\SitemapController;
use Jinstan\Core\Application\SEO\Queries\SitemapQuery;
use Jinstan\Core\View\Presenters\Frontend\SitemapPresenter;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;
use Jinstan\Core\Http\Controllers\Frontend\PublicContentController;
use Jinstan\Core\Application\Content\Queries\PublicContentQuery;
use Jinstan\Core\View\Presenters\Frontend\PublicContentPresenter;
use Jinstan\Core\View\ThemeView;
use Jinstan\Core\Application\Content\Queries\PublicHomepageQuery;
use Jinstan\Core\Application\Content\Queries\PublicNotFoundQuery;
use Jinstan\Core\Http\Controllers\Frontend\PublicMediaController;
use Jinstan\Core\Infrastructure\Filesystem\Content\PublicMediaFileReader;
use Jinstan\Core\Application\Content\Queries\PublishedMediaAccessPolicy;
use Jinstan\Core\Content\PublicMediaAccessPolicy;
use Jinstan\Core\Infrastructure\Filesystem\Theme\ActiveThemeLocator;
use Jinstan\Core\Infrastructure\View\TemplateCatalog;
use Jinstan\Core\Infrastructure\View\ThemeRenderer;

final class ThemeServiceProvider implements ServiceProvider
{
    public function register(Application $application): void
    {
        $container = $application->container();
        if (!$container->has(Connection::class)) {
            return;
        }

        $connection = $container->get(Connection::class);
        $settings = $container->get(SettingsReadRepository::class);
        $types = $container->get(ContentTypeRegistry::class);
        $contents = $container->get(ContentRepositoryContract::class);
        $taxonomy = $container->get(TaxonomyReadRepository::class);

        $navigation = new NavigationRepository($connection);
        $components = new ComponentRegistry();
        $components->register(new BreadcrumbComponent($types));
        $components->register(new RelatedPostsComponent($contents));
        $components->register(new LatestContentComponent($contents, $types, $settings));
        $components->register(new NavigationMenuComponent($navigation, $settings));

        $themeLocator = new ActiveThemeLocator($application->projectPath('themes'));
        $themePath = $themeLocator->locate(
            (string) ($settings->get('site.theme', 'default') ?? 'default'),
        );
        $templates = new TemplateCatalog($themePath);
        $contentService = new ContentService(
            $types,
            $contents,
            $taxonomy,
            $templates,
            new ProtectedSlug($types, $settings),
            $container->get(EventDispatcher::class),
        );
        $theme = new ThemeRenderer(
            $themePath,
            $types,
            $components,
            $application->projectPath('plugins'),
            $settings,
        );
        $redirects = new RedirectRepository($connection);
        $schema = new SchemaRegistry();
        $sitemapQuery = new SitemapQuery($contents, $types, $settings);
        $sitemapPresenter = new SitemapPresenter();
        $publicHomepageQuery = new PublicHomepageQuery($contents, $settings, $types);
        $publicContentPresenter = new PublicContentPresenter($theme, $schema);
        $publicContentQuery = new PublicContentQuery($contents, $settings, $types, $redirects);
        $publicNotFoundQuery = new PublicNotFoundQuery($contents, $settings, $types);
        $publicMediaAccess = new PublishedMediaAccessPolicy($contents, $types);
        $publicMediaFiles = new PublicMediaFileReader(
            $container->get(ContentAttachmentStorage::class),
            $publicMediaAccess,
        );
        $responses = $container->get(ResponseFactory::class);
        $publicContent = new PublicContentController(
            $publicHomepageQuery,
            $publicContentPresenter,
            $publicContentQuery,
            $publicNotFoundQuery,
            $responses,
        );

        $container->set(NavigationRepository::class, $navigation);
        $container->set(NavigationReadRepository::class, $navigation);
        $container->set(NavigationWriteRepository::class, $navigation);
        $container->set(NavigationRepositoryContract::class, $navigation);
        $container->set(ComponentRegistry::class, $components);
        $container->set(TemplateCatalog::class, $templates);
        $container->set(TemplateCatalogReader::class, $templates);
        $container->set(PublishedMediaAccessPolicy::class, $publicMediaAccess);
        $container->set(PublicMediaAccessPolicy::class, $publicMediaAccess);
        $container->set(ContentService::class, $contentService);
        $container->set(ThemeRenderer::class, $theme);
        $container->set(ThemeView::class, $theme);
        $container->set(RedirectRepository::class, $redirects);
        $container->set(RedirectReadRepository::class, $redirects);
        $container->set(RedirectWriteRepository::class, $redirects);
        $container->set(RedirectRepositoryContract::class, $redirects);
        $container->set(SchemaRegistry::class, $schema);
        $container->set(SchemaRegistrar::class, $schema);
        $container->set(SitemapQuery::class, $sitemapQuery);
        $container->set(SitemapPresenter::class, $sitemapPresenter);
        $container->set(PublicHomepageQuery::class, $publicHomepageQuery);
        $container->set(PublicContentPresenter::class, $publicContentPresenter);
        $container->set(PublicContentQuery::class, $publicContentQuery);
        $container->set(PublicNotFoundQuery::class, $publicNotFoundQuery);
        $container->set(PublicMediaFileReader::class, $publicMediaFiles);
        $container->set(PublicMediaReader::class, $publicMediaFiles);
        $sitemapController = new SitemapController(
            $sitemapQuery,
            $sitemapPresenter,
            $responses,
        );
        $container->set(SitemapController::class, $sitemapController);
        $container->set(PublicContentController::class, $publicContent);
        $publicMediaController = new PublicMediaController(
            $publicMediaFiles,
            $responses,
        );
        $container->set(PublicMediaController::class, $publicMediaController);
    }

    public function boot(Application $application): void
    {
    }

}
