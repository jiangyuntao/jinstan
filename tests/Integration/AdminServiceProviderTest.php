<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Application\System\Services\CacheLogsMaintenance;
use Jinstan\Core\View\Presenters\Admin\CacheLogsPresenter;
use Jinstan\Core\Application\System\Queries\CacheLogsQuery;
use Jinstan\Core\Http\Controllers\Admin\ContentAdminController;
use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\View\Presenters\Admin\ContentFormPresenter;
use Jinstan\Core\Application\Content\Queries\ContentListQuery;
use Jinstan\Core\Application\Content\Services\CreateContent;
use Jinstan\Core\Application\Content\Services\DeleteContent;
use Jinstan\Core\Http\Controllers\Admin\DashboardController;
use Jinstan\Core\View\Presenters\Admin\DashboardPresenter;
use Jinstan\Core\Application\Dashboard\Queries\DashboardQuery;
use Jinstan\Core\Http\Controllers\Admin\DatabaseToolsController;
use Jinstan\Core\Application\Database\Services\DatabaseMaintenance;
use Jinstan\Core\View\Presenters\Admin\DatabaseToolsPresenter;
use Jinstan\Core\Application\Database\Queries\DatabaseToolsQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationListQuery;
use Jinstan\Core\View\Presenters\Admin\NavigationListPresenter;
use Jinstan\Core\Application\Navigation\Queries\NavigationItemFormQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationTranslationResolver;
use Jinstan\Core\Application\Navigation\Services\NavigationWriter;
use Jinstan\Core\Http\Controllers\Admin\ThemeAdminController;
use Jinstan\Core\Application\Theme\Queries\ThemeManagerQuery;
use Jinstan\Core\Application\Theme\Services\ThemeWriter;
use Jinstan\Core\View\Presenters\Admin\SystemSettingsPresenter;
use Jinstan\Core\Application\Settings\Services\SaveSystemSettings;
use Jinstan\Core\Application\Taxonomy\Services\ReorderTaxonomyTerms;
use Jinstan\Core\View\Presenters\Admin\RoleAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\RoleAdminQuery;
use Jinstan\Core\Application\Auth\Services\RoleWriter;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTermListQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTranslationResolver;
use Jinstan\Core\Application\Taxonomy\Services\SubmitTaxonomyForm;
use Jinstan\Core\View\Presenters\Admin\TaxonomyFormPresenter;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyFormQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyIndexQuery;
use Jinstan\Core\View\Presenters\Admin\TaxonomyListPresenter;
use Jinstan\Core\Application\Content\Services\UpdateContent;
use Jinstan\Core\Application\Auth\Services\UserWriter;
use Jinstan\Core\View\Presenters\Admin\UserAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\UserAdminQuery;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Auth\AuthenticationReadRepository;
use Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository;
use Jinstan\Core\Auth\RoleReadRepository;
use Jinstan\Core\Infrastructure\Persistence\Auth\RoleRepository;
use Jinstan\Core\Auth\RoleRepositoryContract;
use Jinstan\Core\Auth\RoleWriteRepository;
use Jinstan\Core\Http\Controllers\Admin\LoginController;
use Jinstan\Core\Auth\UserReadRepository;
use Jinstan\Core\Infrastructure\Persistence\Auth\UserRepository;
use Jinstan\Core\Auth\UserRepositoryContract;
use Jinstan\Core\Auth\UserWriteRepository;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Providers\AdminServiceProvider;
use Jinstan\Core\Providers\ContentServiceProvider;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Providers\ThemeServiceProvider;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager;
use Jinstan\Core\Infrastructure\Filesystem\Backup\BackupService;
use Jinstan\Tests\Support\InstalledTestProject;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AdminServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testItDoesNotRegisterAdminServicesBeforeInstallation(): void
    {
        $application = $this->application(InstalledTestProject::create(false));

        self::assertFalse($application->container()->has(Authenticator::class));
        self::assertFalse($application->container()->has(DashboardController::class));
    }

    public function testItRegistersAdminAuthenticationViewsAndControllers(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $container = $application->container();

        self::assertInstanceOf(Authenticator::class, $container->get(Authenticator::class));
        self::assertSame(
            $container->get(DatabaseAuthenticationRepository::class),
            $container->get(AuthenticationReadRepository::class),
        );
        self::assertInstanceOf(AdminViewRenderer::class, $container->get(AdminViewRenderer::class));
        self::assertFalse($container->has(\Jinstan\Core\View\ViewPathResolver::class));
        self::assertFalse($container->has(\Jinstan\Core\Infrastructure\View\LocalViewPathResolver::class));
        self::assertInstanceOf(LoginController::class, $container->get(LoginController::class));
        self::assertInstanceOf(ContentAdminController::class, $container->get(ContentAdminController::class));
        self::assertInstanceOf(ContentAttachmentCoordinator::class, $container->get(ContentAttachmentCoordinator::class));
        self::assertInstanceOf(ContentListQuery::class, $container->get(ContentListQuery::class));
        self::assertInstanceOf(ContentFormPresenter::class, $container->get(ContentFormPresenter::class));
        self::assertInstanceOf(CreateContent::class, $container->get(CreateContent::class));
        self::assertInstanceOf(UpdateContent::class, $container->get(UpdateContent::class));
        self::assertInstanceOf(DeleteContent::class, $container->get(DeleteContent::class));
        self::assertInstanceOf(SystemSettingsPresenter::class, $container->get(SystemSettingsPresenter::class));
        self::assertInstanceOf(SaveSystemSettings::class, $container->get(SaveSystemSettings::class));
        self::assertInstanceOf(ReorderTaxonomyTerms::class, $container->get(ReorderTaxonomyTerms::class));
        self::assertInstanceOf(SubmitTaxonomyForm::class, $container->get(SubmitTaxonomyForm::class));
        self::assertInstanceOf(TaxonomyFormQuery::class, $container->get(TaxonomyFormQuery::class));
        self::assertInstanceOf(TaxonomyFormPresenter::class, $container->get(TaxonomyFormPresenter::class));
        self::assertInstanceOf(TaxonomyIndexQuery::class, $container->get(TaxonomyIndexQuery::class));
        self::assertInstanceOf(TaxonomyListPresenter::class, $container->get(TaxonomyListPresenter::class));
        self::assertInstanceOf(NavigationListQuery::class, $container->get(NavigationListQuery::class));
        self::assertInstanceOf(
            NavigationTranslationResolver::class,
            $container->get(NavigationTranslationResolver::class),
        );
        self::assertInstanceOf(NavigationWriter::class, $container->get(NavigationWriter::class));
        self::assertInstanceOf(NavigationItemFormQuery::class, $container->get(NavigationItemFormQuery::class));
        self::assertInstanceOf(NavigationListPresenter::class, $container->get(NavigationListPresenter::class));
        self::assertInstanceOf(TaxonomyTermListQuery::class, $container->get(TaxonomyTermListQuery::class));
        self::assertInstanceOf(
            TaxonomyTranslationResolver::class,
            $container->get(TaxonomyTranslationResolver::class),
        );
        self::assertInstanceOf(DashboardController::class, $container->get(DashboardController::class));
        self::assertInstanceOf(DashboardQuery::class, $container->get(DashboardQuery::class));
        self::assertInstanceOf(DashboardPresenter::class, $container->get(DashboardPresenter::class));
        self::assertInstanceOf(ThemeAdminController::class, $container->get(ThemeAdminController::class));
        self::assertInstanceOf(ThemeFileManager::class, $container->get(ThemeFileManager::class));
        self::assertInstanceOf(ThemeManagerQuery::class, $container->get(ThemeManagerQuery::class));
        self::assertInstanceOf(ThemeWriter::class, $container->get(ThemeWriter::class));
        self::assertInstanceOf(UserWriter::class, $container->get(UserWriter::class));
        self::assertInstanceOf(UserAdminQuery::class, $container->get(UserAdminQuery::class));
        self::assertInstanceOf(UserAdminPresenter::class, $container->get(UserAdminPresenter::class));
        self::assertInstanceOf(RoleWriter::class, $container->get(RoleWriter::class));
        self::assertInstanceOf(RoleAdminQuery::class, $container->get(RoleAdminQuery::class));
        self::assertInstanceOf(RoleAdminPresenter::class, $container->get(RoleAdminPresenter::class));
        self::assertSame($container->get(RoleRepository::class), $container->get(RoleReadRepository::class));
        self::assertSame($container->get(RoleRepository::class), $container->get(RoleWriteRepository::class));
        self::assertSame($container->get(RoleRepository::class), $container->get(RoleRepositoryContract::class));
        self::assertSame($container->get(UserRepository::class), $container->get(UserReadRepository::class));
        self::assertSame($container->get(UserRepository::class), $container->get(UserWriteRepository::class));
        self::assertSame($container->get(UserRepository::class), $container->get(UserRepositoryContract::class));
        self::assertInstanceOf(DatabaseToolsController::class, $container->get(DatabaseToolsController::class));
        self::assertInstanceOf(DatabaseMaintenance::class, $container->get(DatabaseMaintenance::class));
        self::assertInstanceOf(DatabaseToolsQuery::class, $container->get(DatabaseToolsQuery::class));
        self::assertInstanceOf(DatabaseToolsPresenter::class, $container->get(DatabaseToolsPresenter::class));
        self::assertInstanceOf(CacheLogsQuery::class, $container->get(CacheLogsQuery::class));
        self::assertInstanceOf(CacheLogsPresenter::class, $container->get(CacheLogsPresenter::class));
        self::assertInstanceOf(CacheLogsMaintenance::class, $container->get(CacheLogsMaintenance::class));
        self::assertFalse($container->has(\Jinstan\Core\Application\Database\DatabaseOperations::class));
        self::assertFalse($container->has(\Jinstan\Core\Infrastructure\Database\DatabaseOperationsAdapter::class));
        self::assertFalse($container->has(\Jinstan\Core\Application\System\RuntimeStorageGateway::class));
        self::assertFalse($container->has(\Jinstan\Core\Infrastructure\Filesystem\System\RuntimeStorage::class));
        $response = $container->get(LoginController::class)->show(new ServerRequest('GET', '/login'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<form', (string) $response->getBody());
    }

    private function application(InstalledTestProject $project): Application
    {
        $application = new Application($project->path, 'testing');
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->register(new ContentServiceProvider());
        $application->register(new ThemeServiceProvider());
        $application->container()->get(Router::class)
            ->post('/login', static fn (): null => null)
            ->name('login.store');
        $application->register(new AdminServiceProvider());
        $application->boot();

        return $application;
    }
}
