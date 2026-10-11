<?php

declare(strict_types=1);

use Jinstan\Core\Http\Controllers\Admin\ContentAdminController;
use Jinstan\Core\Http\Controllers\Admin\ContentTypeAdminController;
use Jinstan\Core\Http\Controllers\Admin\CacheLogsController;
use Jinstan\Core\Http\Controllers\Admin\DashboardController;
use Jinstan\Core\Http\Controllers\Admin\DatabaseToolsController;
use Jinstan\Core\Http\Controllers\Admin\LanguageAdminController;
use Jinstan\Core\Http\Controllers\Admin\MediaAdminController;
use Jinstan\Core\Http\Controllers\Admin\NavigationAdminController;
use Jinstan\Core\Http\Controllers\Admin\PluginAdminController;
use Jinstan\Core\Http\Controllers\Admin\RoleAdminController;
use Jinstan\Core\Http\Controllers\Admin\SettingsAdminController;
use Jinstan\Core\Http\Controllers\Admin\TaxonomyAdminController;
use Jinstan\Core\Http\Controllers\Admin\ThemeAdminController;
use Jinstan\Core\Http\Controllers\Admin\UserAdminController;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\Middleware\RequirePermissionMiddleware;
use Jinstan\Core\Http\Middleware\AuthenticateMiddleware;
use Jinstan\Core\Http\Middleware\VerifyCsrfTokenMiddleware;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Routing\UrlGenerator;

return static function (
    RouteRegistrar $routes,
    string $section,
    ?ResponseFactory $responses = null,
    ?UrlGenerator $urls = null,
    ?RequirePermissionMiddleware $contentPermission = null,
    ?AuthenticateMiddleware $authenticate = null,
    ?VerifyCsrfTokenMiddleware $verifyCsrf = null,
    ?RequirePermissionMiddleware $systemPermission = null,
    ?RequirePermissionMiddleware $usersPermission = null,
    ?RequirePermissionMiddleware $settingsPermission = null,
): void {
    if ($section === 'before_plugins') {
        if ($authenticate === null || $verifyCsrf === null || $contentPermission === null) {
            throw new InvalidArgumentException('AuthenticateMiddleware, VerifyCsrfTokenMiddleware and content permission middleware are required for Admin routes.');
        }
        $routes->group(['prefix' => '/admin', 'middleware' => [$authenticate, $verifyCsrf]], static function (RouteRegistrar $routes) use ($contentPermission): void {
        $routes->group(['middleware' => [$contentPermission]], static function (RouteRegistrar $routes): void {
            $routes->get('/media/{first:[a-f0-9]{2}}/{second:[a-f0-9]{2}}/{file:[a-f0-9]{32}\.(?:jpg|jpeg|png|webp|pdf)}', [MediaAdminController::class, 'show'])->name('admin.media.show');
        });

        $routes->group(['middleware' => [$contentPermission]], static function (RouteRegistrar $routes): void {
            $routes->get('/content/{type}/terms/{group}', [ContentAdminController::class, 'termSearch'])->name('admin.content.terms.search');
        });
        $routes->get('/', [DashboardController::class, 'show'])->name('admin.dashboard');
        $routes->group(['middleware' => [$contentPermission]], static function (RouteRegistrar $routes): void {
            $routes->get('/content/{type}', [ContentAdminController::class, 'index'])->name('admin.content.index');
            $routes->get('/content/{type}/create', [ContentAdminController::class, 'create'])->name('admin.content.create');
            $routes->get('/content/{type}/parents', [ContentAdminController::class, 'parentSearch'])->name('admin.content.parents.search');
            $routes->post('/content/{type}', [ContentAdminController::class, 'store'])->name('admin.content.store');
            $routes->get('/content/{type}/{id}/edit', [ContentAdminController::class, 'edit'])->name('admin.content.edit');
            $routes->post('/content/{type}/{id}', [ContentAdminController::class, 'update'])->name('admin.content.update');
            $routes->post('/content/{type}/{id}/delete', [ContentAdminController::class, 'destroy'])->name('admin.content.delete');
        });

        $routes->group(['middleware' => [$contentPermission]], static function (RouteRegistrar $routes): void {
            $routes->get('/content-types', [ContentTypeAdminController::class, 'index'])->name('admin.content-types.index');
            $routes->get('/content-types/{handle}', [ContentTypeAdminController::class, 'edit'])->name('admin.content-types.edit');
            $routes->post('/content-types', [ContentTypeAdminController::class, 'save'])->name('admin.content-types.save');
            $routes->post('/content-types/{handle}/delete', [ContentTypeAdminController::class, 'delete'])->name('admin.content-types.delete');
        });

        $routes->group(['middleware' => [$contentPermission]], static function (RouteRegistrar $routes): void {
            $routes->get('/taxonomies', [TaxonomyAdminController::class, 'index'])->name('admin.taxonomies.index');
            $routes->get('/taxonomies/new', [TaxonomyAdminController::class, 'taxonomyForm'])->name('admin.taxonomies.create');
            $routes->post('/taxonomies/new', [TaxonomyAdminController::class, 'taxonomyForm'])->name('admin.taxonomies.store');
            $routes->get('/taxonomies/{handle}/edit', [TaxonomyAdminController::class, 'taxonomyForm'])->name('admin.taxonomies.edit');
            $routes->post('/taxonomies/{handle}/edit', [TaxonomyAdminController::class, 'taxonomyForm'])->name('admin.taxonomies.update');
            $routes->get('/taxonomies/{handle}/delete', [TaxonomyAdminController::class, 'taxonomyForm'])->name('admin.taxonomies.delete.confirm');
            $routes->post('/taxonomies/{handle}/delete', [TaxonomyAdminController::class, 'taxonomyForm'])->name('admin.taxonomies.delete');
            $routes->get('/taxonomies/{handle}/terms/new', [TaxonomyAdminController::class, 'termForm'])->name('admin.taxonomy-terms.create');
            $routes->post('/taxonomies/{handle}/terms/new', [TaxonomyAdminController::class, 'termForm'])->name('admin.taxonomy-terms.store');
            $routes->get('/taxonomies/{handle}/terms/{id:\d+}/edit', [TaxonomyAdminController::class, 'termForm'])->name('admin.taxonomy-terms.edit');
            $routes->post('/taxonomies/{handle}/terms/{id:\d+}/edit', [TaxonomyAdminController::class, 'termForm'])->name('admin.taxonomy-terms.update');
            $routes->get('/taxonomies/{handle}/terms/{id:\d+}/delete', [TaxonomyAdminController::class, 'termForm'])->name('admin.taxonomy-terms.delete.confirm');
            $routes->post('/taxonomies/{handle}/terms/{id:\d+}/delete', [TaxonomyAdminController::class, 'termForm'])->name('admin.taxonomy-terms.delete');
            $routes->post('/taxonomies/terms/reorder', [TaxonomyAdminController::class, 'reorderTerms'])->name('admin.taxonomy-terms.reorder');
        });

        $routes->group(['middleware' => [$contentPermission]], static function (RouteRegistrar $routes): void {
            $routes->get('/navigation', [NavigationAdminController::class, 'index'])->name('admin.navigation.index');
            $routes->post('/navigation', [NavigationAdminController::class, 'createMenu'])->name('admin.navigation.store');
            $routes->get('/navigation/items/new', [NavigationAdminController::class, 'itemForm'])->name('admin.navigation-items.create');
            $routes->post('/navigation/items', [NavigationAdminController::class, 'createItem'])->name('admin.navigation-items.store');
            $routes->post('/navigation/items/reorder', [NavigationAdminController::class, 'reorderItems'])->name('admin.navigation-items.reorder');
            $routes->post('/navigation/items/{id}', [NavigationAdminController::class, 'updateItem'])->name('admin.navigation-items.update');
            $routes->get('/navigation/items/{id}/edit', [NavigationAdminController::class, 'itemForm'])->name('admin.navigation-items.edit');
            $routes->post('/navigation/items/{id}/delete', [NavigationAdminController::class, 'deleteItem'])->name('admin.navigation-items.delete');
        });
        });

        return;
    }

    if ($section === 'after_plugins') {
        if ($responses === null || $urls === null || $authenticate === null || $verifyCsrf === null || $systemPermission === null || $usersPermission === null || $settingsPermission === null) {
            throw new InvalidArgumentException('ResponseFactory, UrlGenerator and security middleware are required for after_plugins Admin routes.');
        }

        $routes->group(['prefix' => '/admin', 'middleware' => [$authenticate, $verifyCsrf]], static function (RouteRegistrar $routes) use ($responses, $urls, $systemPermission, $usersPermission, $settingsPermission): void {
            $routes->group(['middleware' => [$settingsPermission]], static function (RouteRegistrar $routes): void {
                $routes->get('/plugins', [PluginAdminController::class, 'index'])->name('admin.plugins.index');
                $routes->get('/plugins/{name}/settings', [PluginAdminController::class, 'settings'])->name('admin.plugins.settings');
                $routes->post('/plugins/{name}', [PluginAdminController::class, 'toggle'])->name('admin.plugins.toggle');
                $routes->post('/plugins/{name}/settings', [PluginAdminController::class, 'saveSettings'])->name('admin.plugins.settings.save');
                $routes->post('/plugins/{name}/uninstall', [PluginAdminController::class, 'uninstall'])->name('admin.plugins.uninstall');
            });

            $routes->group(['middleware' => [$systemPermission]], static function (RouteRegistrar $routes): void {
                $routes->get('/themes', [ThemeAdminController::class, 'show'])->name('admin.themes.index');
                $routes->post('/themes', [ThemeAdminController::class, 'save'])->name('admin.themes.save');
                $routes->post('/themes/create', [ThemeAdminController::class, 'createTheme'])->name('admin.themes.create');
                $routes->post('/themes/delete', [ThemeAdminController::class, 'deleteTheme'])->name('admin.themes.delete');
                $routes->post('/themes/directories/create', [ThemeAdminController::class, 'createDirectory'])->name('admin.themes.directories.create');
                $routes->post('/themes/directories/rename', [ThemeAdminController::class, 'renameDirectory'])->name('admin.themes.directories.rename');
                $routes->post('/themes/directories/delete', [ThemeAdminController::class, 'deleteDirectory'])->name('admin.themes.directories.delete');
                $routes->post('/themes/files/create', [ThemeAdminController::class, 'createFile'])->name('admin.themes.files.create');
                $routes->post('/themes/files/delete', [ThemeAdminController::class, 'deleteFile'])->name('admin.themes.files.delete');
            });

            $routes->group(['middleware' => [$systemPermission]], static function (RouteRegistrar $routes): void {
                $routes->get('/database', [DatabaseToolsController::class, 'show'])->name('admin.database.index');
                $routes->post('/database/check', [DatabaseToolsController::class, 'check'])->name('admin.database.check');
                $routes->post('/database/optimize', [DatabaseToolsController::class, 'optimize'])->name('admin.database.optimize');
                $routes->post('/database/cleanup', [DatabaseToolsController::class, 'cleanup'])->name('admin.database.cleanup');
                $routes->post('/database/backup', [DatabaseToolsController::class, 'backup'])->name('admin.database.backup');
                $routes->post('/database/backups/{id:\d{14}}/delete', [DatabaseToolsController::class, 'deleteBackup'])->name('admin.database.backups.delete');
            });

            $routes->group(['middleware' => [$usersPermission]], static function (RouteRegistrar $routes): void {
                $routes->get('/users', [UserAdminController::class, 'index'])->name('admin.users.index');
                $routes->get('/users/new', [UserAdminController::class, 'form'])->name('admin.users.create');
                $routes->post('/users/new', [UserAdminController::class, 'save'])->name('admin.users.store');
                $routes->get('/users/{id:\d+}/edit', [UserAdminController::class, 'form'])->name('admin.users.edit');
                $routes->post('/users/{id:\d+}/edit', [UserAdminController::class, 'save'])->name('admin.users.update');
                $routes->post('/users/{id:\d+}/delete', [UserAdminController::class, 'delete'])->name('admin.users.delete');

                $routes->get('/roles', [RoleAdminController::class, 'index'])->name('admin.roles.index');
                $routes->get('/roles/new', [RoleAdminController::class, 'form'])->name('admin.roles.create');
                $routes->post('/roles/new', [RoleAdminController::class, 'save'])->name('admin.roles.store');
                $routes->get('/roles/{key:[a-z][a-z0-9._-]+}/edit', [RoleAdminController::class, 'form'])->name('admin.roles.edit');
                $routes->post('/roles/{key:[a-z][a-z0-9._-]+}/edit', [RoleAdminController::class, 'save'])->name('admin.roles.update');
                $routes->post('/roles/{key:[a-z][a-z0-9._-]+}/delete', [RoleAdminController::class, 'delete'])->name('admin.roles.delete');
            });
            $routes->get('/profile', [UserAdminController::class, 'profile'])->name('admin.profile');
            $routes->post('/profile', [UserAdminController::class, 'saveProfile'])->name('admin.profile.update');
        });
        $routes->post('/logout', [UserAdminController::class, 'logout'])->name('logout');
        $routes->group(['prefix' => '/admin', 'middleware' => [$authenticate, $verifyCsrf]], static function (RouteRegistrar $routes) use ($systemPermission, $settingsPermission): void {
            $routes->group(['middleware' => [$settingsPermission]], static function (RouteRegistrar $routes): void {
                $routes->get('/settings/system', [SettingsAdminController::class, 'system'])->name('admin.settings.system');
                $routes->post('/settings/system', [SettingsAdminController::class, 'saveSystem'])->name('admin.settings.system.save');

                $routes->get('/settings/languages', [LanguageAdminController::class, 'index'])->name('admin.languages.index');
                $routes->get('/settings/languages/new', [LanguageAdminController::class, 'form'])->name('admin.languages.create');
                $routes->post('/settings/languages/new', [LanguageAdminController::class, 'save'])->name('admin.languages.store');
                $routes->get('/settings/languages/{code:[A-Za-z0-9-]+}/edit', [LanguageAdminController::class, 'form'])->name('admin.languages.edit');
                $routes->post('/settings/languages/{code:[A-Za-z0-9-]+}/edit', [LanguageAdminController::class, 'save'])->name('admin.languages.update');
                $routes->post('/settings/languages/{code:[A-Za-z0-9-]+}/delete', [LanguageAdminController::class, 'delete'])->name('admin.languages.delete');
            });

            $routes->group(['middleware' => [$systemPermission]], static function (RouteRegistrar $routes): void {
                $routes->get('/cache', [CacheLogsController::class, 'showCache'])->name('admin.cache.index');
                $routes->post('/cache/clear', [CacheLogsController::class, 'clearCache'])->name('admin.cache.clear');
                $routes->get('/logs', [CacheLogsController::class, 'showLogs'])->name('admin.logs.index');
                $routes->post('/logs/clear', [CacheLogsController::class, 'clearLogs'])->name('admin.logs.clear');
            });
        });

        return;
    }

    if ($section === 'after_content') {
        return;
    }

    throw new InvalidArgumentException(sprintf('Unknown Admin route section: %s', $section));
};
