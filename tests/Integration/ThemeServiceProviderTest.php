<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Queries\PublicContentQuery;
use Jinstan\Core\Application\Content\Queries\PublicHomepageQuery;
use Jinstan\Core\Application\Content\Queries\PublicNotFoundQuery;
use Jinstan\Core\Component\ComponentRegistry;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Navigation\NavigationReadRepository;
use Jinstan\Core\Navigation\NavigationRepositoryContract;
use Jinstan\Core\Navigation\NavigationWriteRepository;
use Jinstan\Core\Providers\ContentServiceProvider;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Providers\ThemeServiceProvider;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Application\SEO\Queries\SitemapQuery;
use Jinstan\Core\View\Presenters\Frontend\SitemapPresenter;
use Jinstan\Core\SEO\RedirectReadRepository;
use Jinstan\Core\Infrastructure\Persistence\SEO\RedirectRepository;
use Jinstan\Core\SEO\RedirectRepositoryContract;
use Jinstan\Core\SEO\RedirectWriteRepository;
use Jinstan\Core\Http\Controllers\Frontend\PublicContentController;
use Jinstan\Core\View\Presenters\Frontend\PublicContentPresenter;
use Jinstan\Core\Infrastructure\Filesystem\Content\PublicMediaFileReader;
use Jinstan\Core\Infrastructure\View\TemplateCatalog;
use Jinstan\Core\Infrastructure\View\ThemeRenderer;
use Jinstan\Tests\Support\InstalledTestProject;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ThemeServiceProviderTest extends TestCase
{
    public function testItDoesNotRegisterThemeServicesBeforeInstallation(): void
    {
        $application = $this->application(InstalledTestProject::create(false));

        self::assertFalse($application->container()->has(ThemeRenderer::class));
        self::assertFalse($application->container()->has(PublicContentController::class));
    }

    public function testItRegistersThemeContentAndComponentServices(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $container = $application->container();

        self::assertInstanceOf(NavigationRepository::class, $container->get(NavigationRepository::class));
        self::assertSame($container->get(NavigationRepository::class), $container->get(NavigationReadRepository::class));
        self::assertSame($container->get(NavigationRepository::class), $container->get(NavigationWriteRepository::class));
        self::assertSame($container->get(NavigationRepository::class), $container->get(NavigationRepositoryContract::class));
        self::assertInstanceOf(ComponentRegistry::class, $container->get(ComponentRegistry::class));
        self::assertFalse($container->has(\Jinstan\Core\Infrastructure\Filesystem\Theme\ActiveThemeLocator::class));
        self::assertInstanceOf(TemplateCatalog::class, $container->get(TemplateCatalog::class));
        self::assertInstanceOf(ContentService::class, $container->get(ContentService::class));
        self::assertInstanceOf(ThemeRenderer::class, $container->get(ThemeRenderer::class));
        self::assertInstanceOf(SitemapQuery::class, $container->get(SitemapQuery::class));
        self::assertInstanceOf(SitemapPresenter::class, $container->get(SitemapPresenter::class));
        self::assertSame($container->get(RedirectRepository::class), $container->get(RedirectReadRepository::class));
        self::assertSame($container->get(RedirectRepository::class), $container->get(RedirectWriteRepository::class));
        self::assertSame($container->get(RedirectRepository::class), $container->get(RedirectRepositoryContract::class));
        self::assertInstanceOf(PublicHomepageQuery::class, $container->get(PublicHomepageQuery::class));
        self::assertInstanceOf(PublicContentPresenter::class, $container->get(PublicContentPresenter::class));
        self::assertInstanceOf(PublicContentQuery::class, $container->get(PublicContentQuery::class));
        self::assertInstanceOf(PublicNotFoundQuery::class, $container->get(PublicNotFoundQuery::class));
        self::assertInstanceOf(PublicMediaFileReader::class, $container->get(PublicMediaFileReader::class));

        $response = $container->get(PublicContentController::class)->homepage(
            new ServerRequest('GET', '/'),
            [],
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame('', (string) $response->getBody());
    }

    public function testInvalidThemeSettingFallsBackInsideTheThemesDirectory(): void
    {
        $project = InstalledTestProject::create();
        (new SettingsRepository($project->connection()))->set('site.theme', '../outside');

        $application = $this->application($project);
        $response = $application->container()->get(PublicContentController::class)->homepage(
            new ServerRequest('GET', '/'),
            [],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Baseline Site', (string) $response->getBody());
    }

    private function application(InstalledTestProject $project): Application
    {
        $application = new Application($project->path, 'testing');
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->register(new ContentServiceProvider());
        $application->register(new ThemeServiceProvider());
        $application->boot();

        return $application;
    }
}
