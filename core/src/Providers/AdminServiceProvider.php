<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Infrastructure\View\LocalViewPathResolver;
use Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository;
use Jinstan\Core\Application\System\Services\CacheLogsMaintenance;
use Jinstan\Core\View\Presenters\Admin\CacheLogsPresenter;
use Jinstan\Core\Application\System\Queries\CacheLogsQuery;
use Jinstan\Core\Http\Controllers\Admin\ContentAdminController;
use Jinstan\Core\Http\Requests\Admin\ContentRequest;
use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\Application\Content\Queries\ContentEditQuery;
use Jinstan\Core\View\Presenters\Admin\ContentFormPresenter;
use Jinstan\Core\View\Presenters\Admin\ContentListPresenter;
use Jinstan\Core\Application\Content\Queries\ContentListQuery;
use Jinstan\Core\Application\Content\Queries\ContentSearchQuery;
use Jinstan\Core\Application\Content\Services\CreateContent;
use Jinstan\Core\Application\Content\Services\DeleteContent;
use Jinstan\Core\Http\Controllers\Admin\ContentTypeAdminController;
use Jinstan\Core\View\Presenters\Admin\DashboardPresenter;
use Jinstan\Core\Application\Dashboard\Queries\DashboardQuery;
use Jinstan\Core\Http\Controllers\Admin\DatabaseToolsController;
use Jinstan\Core\Application\Database\Services\DatabaseMaintenance;
use Jinstan\Core\View\Presenters\Admin\DatabaseToolsPresenter;
use Jinstan\Core\Application\Database\Queries\DatabaseToolsQuery;
use Jinstan\Core\Application\Database\BackupGateway;
use Jinstan\Core\Http\Controllers\Admin\DashboardController;
use Jinstan\Core\Http\Controllers\Admin\LanguageAdminController;
use Jinstan\Core\Http\Controllers\Admin\CacheLogsController;
use Jinstan\Core\Http\Controllers\Admin\MediaAdminController;
use Jinstan\Core\Http\Controllers\Admin\NavigationAdminController;
use Jinstan\Core\Application\Navigation\Queries\NavigationListQuery;
use Jinstan\Core\View\Presenters\Admin\NavigationListPresenter;
use Jinstan\Core\Application\Navigation\Queries\NavigationItemFormQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationTranslationResolver;
use Jinstan\Core\Application\Navigation\Services\NavigationWriter;
use Jinstan\Core\Http\Controllers\Admin\RoleAdminController;
use Jinstan\Core\View\Presenters\Admin\RoleAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\RoleAdminQuery;
use Jinstan\Core\Application\Auth\Services\RoleWriter;
use Jinstan\Core\Application\Taxonomy\Services\ReorderTaxonomyTerms;
use Jinstan\Core\Application\Settings\Services\SaveSystemSettings;
use Jinstan\Core\Http\Controllers\Admin\SettingsAdminController;
use Jinstan\Core\View\Presenters\Admin\SystemSettingsPresenter;
use Jinstan\Core\Application\Taxonomy\Services\SubmitTaxonomyForm;
use Jinstan\Core\Http\Controllers\Admin\TaxonomyAdminController;
use Jinstan\Core\View\Presenters\Admin\TaxonomyFormPresenter;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyFormQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyIndexQuery;
use Jinstan\Core\View\Presenters\Admin\TaxonomyListPresenter;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTermListQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTranslationResolver;
use Jinstan\Core\Http\Controllers\Admin\ThemeAdminController;
use Jinstan\Core\Application\Theme\Queries\ThemeManagerQuery;
use Jinstan\Core\Application\Theme\Services\ThemeWriter;
use Jinstan\Core\Http\Controllers\Admin\UserAdminController;
use Jinstan\Core\View\Presenters\Admin\UserAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\UserAdminQuery;
use Jinstan\Core\Application\Auth\Services\UserWriter;
use Jinstan\Core\Application\Content\Services\UpdateContent;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Auth\AuthenticationReadRepository;
use Jinstan\Core\Http\Controllers\Admin\LoginController;
use Jinstan\Core\Http\Requests\Auth\LoginRequest;
use Jinstan\Core\Infrastructure\Persistence\Auth\RoleRepository;
use Jinstan\Core\Auth\RoleReadRepository;
use Jinstan\Core\Auth\RoleRepositoryContract;
use Jinstan\Core\Auth\RoleWriteRepository;
use Jinstan\Core\Http\Requests\Admin\RoleRequest;
use Jinstan\Core\Infrastructure\Persistence\Auth\UserRepository;
use Jinstan\Core\Auth\UserReadRepository;
use Jinstan\Core\Auth\UserRepositoryContract;
use Jinstan\Core\Auth\UserWriteRepository;
use Jinstan\Core\Http\Requests\Admin\UserRequest;
use Jinstan\Core\Infrastructure\Filesystem\Backup\BackupService;
use Jinstan\Core\Infrastructure\Database\DatabaseOperationsAdapter;
use Jinstan\Core\Infrastructure\Filesystem\System\RuntimeStorage;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Application\Content\Services\ContentTypeDeletionService;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\I18n\Translator;
use Jinstan\Core\I18n\TranslationRegistry;
use Jinstan\Core\Event\EventDispatcher;
use Jinstan\Core\I18n\AdminLocale;
use Jinstan\Core\I18n\TranslationCatalogLoader;
use Jinstan\Core\Navigation\NavigationRepositoryContract;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Security\RateLimiter;
use Jinstan\Core\Settings\SettingsRepositoryContract;
use Jinstan\Core\Http\Requests\Admin\SystemSettingsRequest;
use Jinstan\Core\Support\FileSizeFormatter;
use Jinstan\Core\Taxonomy\TaxonomyRepositoryContract;
use Jinstan\Core\Infrastructure\View\TemplateCatalog;
use Jinstan\Core\View\ViewNamespaceRegistry;
use Jinstan\Core\View\AdminView;
use Jinstan\Core\View\ViewRegistrar;
use Jinstan\Core\View\ViewRenderer;
use Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager;
use Jinstan\Core\Application\Theme\ThemeFileGateway;
use Jinstan\Core\Application\Theme\ThemeSelectionGateway;

final class AdminServiceProvider implements ServiceProvider
{
    public function register(Application $application): void
    {
        $container = $application->container();
        if (!$container->has(Connection::class)) {
            return;
        }

        $connection = $container->get(Connection::class);
        $settings = $container->get(SettingsRepositoryContract::class);
        $types = $container->get(ContentTypeRegistry::class);
        $contents = $container->get(ContentRepository::class);
        $taxonomy = $container->get(TaxonomyRepositoryContract::class);
        $attachments = $container->get(ContentAttachmentStorage::class);
        $templates = $container->get(TemplateCatalog::class);
        $contentService = $container->get(ContentService::class);
        $navigation = $container->get(NavigationRepositoryContract::class);
        $router = $container->get(Router::class);
        $csrf = $container->get(CsrfToken::class);
        $responses = $container->get(ResponseFactory::class);
        $urls = $container->get(UrlGenerator::class);

        $authentication = new DatabaseAuthenticationRepository($connection);
        $auth = new Authenticator($authentication);
        $catalogs = $container->get(TranslationCatalogLoader::class);
        $adminLocale = new AdminLocale($application->projectPath('core/lang/admin'), catalogs: $catalogs);
        $adminLocale->use($settings->get('admin.locale', 'zh-CN') ?? 'zh-CN');
        $translator = new Translator(
            $application->projectPath('core/lang/admin'),
            $adminLocale->current(...),
            extensions: $container->get(TranslationRegistry::class),
            loader: $catalogs,
        );
        $viewPathResolver = new LocalViewPathResolver();
        $viewNamespaces = new ViewNamespaceRegistry($viewPathResolver);
        $views = new AdminViewRenderer(
            $application->projectPath('core/resources/views/admin'),
            $application->projectPath('storage/cache/latte-admin'),
            $urls,
            null,
            $translator,
            fn (string $permission): bool => $auth->can($permission),
            $types,
            $viewNamespaces,
        );
        $form = new AdminContentForm($urls);
        $roles = new RoleRepository($connection);
        $roleRequest = new RoleRequest();
        $users = new UserRepository($connection);
        $userRequest = new UserRequest();
        $themeFiles = new ThemeFileManager($application->projectPath('themes'));
        $systemSettingsRequest = new SystemSettingsRequest(
            $settings,
            $themeFiles,
            $contents,
        );
        $systemSettingsPresenter = new SystemSettingsPresenter($contents, $settings, $systemSettingsRequest);
        $saveSystemSettings = new SaveSystemSettings($settings);
        $loginRequest = new LoginRequest();
        $backups = new BackupService($connection, $application->projectPath());
        $database = new DatabaseOperationsAdapter($connection);
        $deletion = new ContentTypeDeletionService(
            $types,
            $container->get(EditableContentTypes::class),
            $contents,
            $attachments,
        );
        $contentAttachments = new ContentAttachmentCoordinator($attachments, $contents, $types);
        $contentListQuery = new ContentListQuery($contents, $settings);
        $contentListPresenter = new ContentListPresenter($views, $csrf, $settings, $urls);
        $contentFormPresenter = new ContentFormPresenter($form, $csrf, $contents, $settings, $urls, $taxonomy, $templates);
        $contentSearchQuery = new ContentSearchQuery($types, $contents, $taxonomy);
        $contentEditQuery = new ContentEditQuery($contents);
        $createContent = new CreateContent($contentService, $contentAttachments, $settings);
        $updateContent = new UpdateContent($contentService, $contents, $contentAttachments);
        $deleteContent = new DeleteContent(
            $contents,
            $types,
            $contentAttachments,
            $container->get(EventDispatcher::class),
        );
        $contentRequest = new ContentRequest($settings);
        $taxonomyTranslations = new TaxonomyTranslationResolver($taxonomy, $settings);
        $taxonomyTermListQuery = new TaxonomyTermListQuery($taxonomy, $settings, $taxonomyTranslations);
        $reorderTaxonomyTerms = new ReorderTaxonomyTerms($taxonomy);
        $submitTaxonomyForm = new SubmitTaxonomyForm($taxonomy, $types);
        $taxonomyFormQuery = new TaxonomyFormQuery($taxonomy);
        $taxonomyFormPresenter = new TaxonomyFormPresenter($views, $settings, $taxonomyTranslations);
        $taxonomyIndexQuery = new TaxonomyIndexQuery($taxonomy);
        $taxonomyListPresenter = new TaxonomyListPresenter($views, $settings, $urls);
        $navigationTranslations = new NavigationTranslationResolver($navigation, $settings);
        $navigationListQuery = new NavigationListQuery($navigation, $settings, $navigationTranslations);
        $navigationWriter = new NavigationWriter($navigation, $settings);
        $navigationItemFormQuery = new NavigationItemFormQuery($navigation, $settings, $navigationTranslations);
        $navigationListPresenter = new NavigationListPresenter($views);
        $themeManagerQuery = new ThemeManagerQuery($themeFiles, $settings);
        $themeWriter = new ThemeWriter($themeFiles, $settings);
        $userWriter = new UserWriter($auth, $users, $roles);
        $userAdminQuery = new UserAdminQuery($users, $roles);
        $userAdminPresenter = new UserAdminPresenter($views, $csrf);
        $roleWriter = new RoleWriter($roles);
        $roleAdminQuery = new RoleAdminQuery($roles);
        $roleAdminPresenter = new RoleAdminPresenter($views, $csrf);
        $databaseToolsQuery = new DatabaseToolsQuery($database, $backups);
        $databaseToolsPresenter = new DatabaseToolsPresenter($views, $csrf);
        $databaseMaintenance = new DatabaseMaintenance($database, $backups);
        $storagePath = $application->projectPath('storage');
        $runtimeStorage = new RuntimeStorage($storagePath);
        $fileSizes = new FileSizeFormatter();
        $cacheLogsQuery = new CacheLogsQuery($runtimeStorage);
        $cacheLogsPresenter = new CacheLogsPresenter($views, $csrf, $fileSizes);
        $cacheLogsMaintenance = new CacheLogsMaintenance($runtimeStorage);
        $dashboardQuery = new DashboardQuery($contents, $database, $types);
        $dashboardPresenter = new DashboardPresenter($views, $urls);

        $container->set(Authenticator::class, $auth);
        $container->set(AuthenticationReadRepository::class, $authentication);
        $container->set(DatabaseAuthenticationRepository::class, $authentication);
        $container->set(AdminLocale::class, $adminLocale);
        $container->set(Translator::class, $translator);
        $container->set(AdminViewRenderer::class, $views);
        $container->set(AdminView::class, $views);
        $container->set(ViewNamespaceRegistry::class, $viewNamespaces);
        $container->set(ViewRegistrar::class, $viewNamespaces);
        $container->set(ViewRenderer::class, $views);
        $container->set(AdminContentForm::class, $form);
        $container->set(RoleRepository::class, $roles);
        $container->set(RoleReadRepository::class, $roles);
        $container->set(RoleWriteRepository::class, $roles);
        $container->set(RoleRepositoryContract::class, $roles);
        $container->set(RoleRequest::class, $roleRequest);
        $container->set(UserRepository::class, $users);
        $container->set(UserReadRepository::class, $users);
        $container->set(UserWriteRepository::class, $users);
        $container->set(UserRepositoryContract::class, $users);
        $container->set(UserRequest::class, $userRequest);
        $container->set(SystemSettingsRequest::class, $systemSettingsRequest);
        $container->set(SystemSettingsPresenter::class, $systemSettingsPresenter);
        $container->set(SaveSystemSettings::class, $saveSystemSettings);
        $container->set(LoginRequest::class, $loginRequest);
        $container->set(BackupService::class, $backups);
        $container->set(BackupGateway::class, $backups);
        $container->set(ContentTypeDeletionService::class, $deletion);
        $container->set(ContentAttachmentCoordinator::class, $contentAttachments);
        $container->set(ContentListQuery::class, $contentListQuery);
        $container->set(ContentListPresenter::class, $contentListPresenter);
        $container->set(ContentFormPresenter::class, $contentFormPresenter);
        $container->set(ContentSearchQuery::class, $contentSearchQuery);
        $container->set(ContentEditQuery::class, $contentEditQuery);
        $container->set(CreateContent::class, $createContent);
        $container->set(UpdateContent::class, $updateContent);
        $container->set(DeleteContent::class, $deleteContent);
        $container->set(TaxonomyTranslationResolver::class, $taxonomyTranslations);
        $container->set(TaxonomyTermListQuery::class, $taxonomyTermListQuery);
        $container->set(ReorderTaxonomyTerms::class, $reorderTaxonomyTerms);
        $container->set(SubmitTaxonomyForm::class, $submitTaxonomyForm);
        $container->set(TaxonomyFormQuery::class, $taxonomyFormQuery);
        $container->set(TaxonomyFormPresenter::class, $taxonomyFormPresenter);
        $container->set(TaxonomyIndexQuery::class, $taxonomyIndexQuery);
        $container->set(TaxonomyListPresenter::class, $taxonomyListPresenter);
        $container->set(NavigationTranslationResolver::class, $navigationTranslations);
        $container->set(NavigationListQuery::class, $navigationListQuery);
        $container->set(NavigationWriter::class, $navigationWriter);
        $container->set(NavigationItemFormQuery::class, $navigationItemFormQuery);
        $container->set(NavigationListPresenter::class, $navigationListPresenter);
        $container->set(ThemeFileManager::class, $themeFiles);
        $container->set(ThemeFileGateway::class, $themeFiles);
        $container->set(ThemeSelectionGateway::class, $themeFiles);
        $container->set(ThemeManagerQuery::class, $themeManagerQuery);
        $container->set(ThemeWriter::class, $themeWriter);
        $container->set(UserWriter::class, $userWriter);
        $container->set(UserAdminQuery::class, $userAdminQuery);
        $container->set(UserAdminPresenter::class, $userAdminPresenter);
        $container->set(RoleWriter::class, $roleWriter);
        $container->set(RoleAdminQuery::class, $roleAdminQuery);
        $container->set(RoleAdminPresenter::class, $roleAdminPresenter);
        $container->set(DatabaseToolsQuery::class, $databaseToolsQuery);
        $container->set(DatabaseToolsPresenter::class, $databaseToolsPresenter);
        $container->set(DatabaseMaintenance::class, $databaseMaintenance);
        $container->set(CacheLogsQuery::class, $cacheLogsQuery);
        $container->set(CacheLogsPresenter::class, $cacheLogsPresenter);
        $container->set(CacheLogsMaintenance::class, $cacheLogsMaintenance);
        $container->set(DashboardQuery::class, $dashboardQuery);
        $container->set(DashboardPresenter::class, $dashboardPresenter);
        $loginController = new LoginController(
            $auth,
            $csrf,
            $views,
            $urls,
            $loginRequest,
            $responses,
            $container->get(RateLimiter::class),
        );
        $container->set(LoginController::class, $loginController);
        $mediaController = new MediaAdminController($attachments, $responses);
        $container->set(MediaAdminController::class, $mediaController);
        $contentController = new ContentAdminController(
            $auth,
            $types,
            $csrf,
            $contentAttachments,
            $contentListQuery,
            $contentListPresenter,
            $contentFormPresenter,
            $contentSearchQuery,
            $contentEditQuery,
            $createContent,
            $updateContent,
            $deleteContent,
            $contentRequest,
            $views,
            $settings,
            $urls,
            $responses,
        );
        $container->set(ContentAdminController::class, $contentController);
        $dashboardController = new DashboardController(
            $dashboardQuery,
            $dashboardPresenter,
            $views,
            $responses,
        );
        $container->set(DashboardController::class, $dashboardController);
        $contentTypeController = new ContentTypeAdminController(
            $types,
            $container->get(EditableContentTypes::class),
            $csrf,
            $views,
            $urls,
            $responses,
            $taxonomy,
            $templates,
            $router,
            $deletion,
        );
        $container->set(ContentTypeAdminController::class, $contentTypeController);
        $taxonomyController = new TaxonomyAdminController(
            $csrf,
            $settings,
            $taxonomyTermListQuery,
            $taxonomyTranslations,
            $reorderTaxonomyTerms,
            $submitTaxonomyForm,
            $taxonomyFormQuery,
            $taxonomyFormPresenter,
            $taxonomyIndexQuery,
            $taxonomyListPresenter,
            $views,
            $urls,
            $responses,
        );
        $container->set(TaxonomyAdminController::class, $taxonomyController);
        $navigationController = new NavigationAdminController(
            $csrf,
            $settings,
            $navigationListQuery,
            $navigationWriter,
            $navigationItemFormQuery,
            $navigationListPresenter,
            $views,
            $urls,
            $responses,
        );
        $container->set(NavigationAdminController::class, $navigationController);
        $themeController = new ThemeAdminController(
            $csrf,
            $themeManagerQuery,
            $themeWriter,
            $views,
            $urls,
            $responses,
        );
        $container->set(ThemeAdminController::class, $themeController);
        $databaseToolsController = new DatabaseToolsController(
            $databaseToolsQuery,
            $databaseToolsPresenter,
            $databaseMaintenance,
            $views,
            $urls,
            $responses,
        );
        $container->set(DatabaseToolsController::class, $databaseToolsController);
        $userController = new UserAdminController(
            $auth,
            $csrf,
            $userWriter,
            $userAdminQuery,
            $userAdminPresenter,
            $views,
            $urls,
            $userRequest,
            $responses,
        );
        $container->set(UserAdminController::class, $userController);
        $roleController = new RoleAdminController(
            $roleWriter,
            $roleAdminQuery,
            $roleAdminPresenter,
            $views,
            $urls,
            $roleRequest,
            $responses,
        );
        $container->set(RoleAdminController::class, $roleController);
        $settingsController = new SettingsAdminController(
            $csrf,
            $systemSettingsPresenter,
            $saveSystemSettings,
            $views,
            $urls,
            $systemSettingsRequest,
            $responses,
        );
        $container->set(SettingsAdminController::class, $settingsController);
        $languageController = new LanguageAdminController($settings, $csrf, $views, $urls, $responses);
        $container->set(LanguageAdminController::class, $languageController);
        $cacheLogsController = new CacheLogsController(
            $cacheLogsQuery,
            $cacheLogsPresenter,
            $cacheLogsMaintenance,
            $views,
            $urls,
            $responses,
        );
        $container->set(CacheLogsController::class, $cacheLogsController);
    }

    public function boot(Application $application): void
    {
    }
}
