<?php

declare(strict_types=1);

namespace Jinstan\Tests\Support;

use Jinstan\Core\Routing\Router;
use Jinstan\Core\Routing\UrlGenerator;

final class TestUrlGenerator
{
    public static function contentAdmin(): UrlGenerator
    {
        return self::with([
            'admin.content.index' => '/admin/content/{type}',
            'admin.content.store' => '/admin/content/{type}',
            'admin.content.edit' => '/admin/content/{type}/{id}/edit',
            'admin.content.update' => '/admin/content/{type}/{id}',
            'admin.content.delete' => '/admin/content/{type}/{id}/delete',
            'admin.content.parents.search' => '/admin/content/{type}/parents',
            'admin.content.terms.search' => '/admin/content/{type}/terms/{group}',
            'admin.media.show' => '/admin/media/{first}/{second}/{file}',
        ]);
    }

    public static function adminDestinations(): UrlGenerator
    {
        return self::with([
            'admin.taxonomies.index' => '/admin/taxonomies',
            'admin.navigation.index' => '/admin/navigation',
            'admin.themes.index' => '/admin/themes',
            'admin.plugins.index' => '/admin/plugins',
            'admin.plugins.settings' => '/admin/plugins/{name}/settings',
            'admin.users.index' => '/admin/users',
            'admin.profile' => '/admin/profile',
            'login.show' => '/login',
            'admin.roles.index' => '/admin/roles',
            'admin.languages.index' => '/admin/settings/languages',
            'admin.settings.system' => '/admin/settings/system',
            'admin.database.index' => '/admin/database',
            'admin.cache.index' => '/admin/cache',
            'admin.logs.index' => '/admin/logs',
            'admin.content-types.index' => '/admin/content-types',
        ]);
    }

    public static function adminViews(): UrlGenerator
    {
        return self::with([
            'home' => '/',
            'login.store' => '/login',
            'logout' => '/logout',
            'admin.dashboard' => '/admin',
            'admin.content.index' => '/admin/content/{type}',
            'admin.content.create' => '/admin/content/{type}/create',
            'admin.content-types.index' => '/admin/content-types',
            'admin.content-types.edit' => '/admin/content-types/{handle}',
            'admin.content-types.save' => '/admin/content-types',
            'admin.content-types.delete' => '/admin/content-types/{handle}/delete',
            'admin.taxonomies.index' => '/admin/taxonomies',
            'admin.taxonomies.create' => '/admin/taxonomies/new',
            'admin.taxonomies.edit' => '/admin/taxonomies/{handle}/edit',
            'admin.taxonomies.delete.confirm' => '/admin/taxonomies/{handle}/delete',
            'admin.taxonomy-terms.create' => '/admin/taxonomies/{handle}/terms/new',
            'admin.taxonomy-terms.edit' => '/admin/taxonomies/{handle}/terms/{id}/edit',
            'admin.taxonomy-terms.delete.confirm' => '/admin/taxonomies/{handle}/terms/{id}/delete',
            'admin.taxonomy-terms.reorder' => '/admin/taxonomies/terms/reorder',
            'admin.navigation.index' => '/admin/navigation',
            'admin.navigation.store' => '/admin/navigation',
            'admin.navigation-items.create' => '/admin/navigation/items/new',
            'admin.navigation-items.store' => '/admin/navigation/items',
            'admin.navigation-items.edit' => '/admin/navigation/items/{id}/edit',
            'admin.navigation-items.update' => '/admin/navigation/items/{id}',
            'admin.navigation-items.delete' => '/admin/navigation/items/{id}/delete',
            'admin.plugins.index' => '/admin/plugins',
            'admin.plugins.settings' => '/admin/plugins/{name}/settings',
            'admin.plugins.settings.save' => '/admin/plugins/{name}/settings',
            'admin.plugins.toggle' => '/admin/plugins/{name}',
            'admin.plugins.uninstall' => '/admin/plugins/{name}/uninstall',
            'admin.themes.index' => '/admin/themes',
            'admin.themes.save' => '/admin/themes',
            'admin.themes.create' => '/admin/themes/create',
            'admin.themes.delete' => '/admin/themes/delete',
            'admin.themes.directories.create' => '/admin/themes/directories/create',
            'admin.themes.directories.rename' => '/admin/themes/directories/rename',
            'admin.themes.directories.delete' => '/admin/themes/directories/delete',
            'admin.themes.files.create' => '/admin/themes/files/create',
            'admin.themes.files.delete' => '/admin/themes/files/delete',
            'admin.database.index' => '/admin/database',
            'admin.database.check' => '/admin/database/check',
            'admin.database.optimize' => '/admin/database/optimize',
            'admin.database.backup' => '/admin/database/backup',
            'admin.database.backups.delete' => '/admin/database/backups/{id}/delete',
            'admin.users.index' => '/admin/users',
            'admin.users.create' => '/admin/users/new',
            'admin.users.edit' => '/admin/users/{id}/edit',
            'admin.users.delete' => '/admin/users/{id}/delete',
            'admin.roles.index' => '/admin/roles',
            'admin.roles.create' => '/admin/roles/new',
            'admin.roles.edit' => '/admin/roles/{key}/edit',
            'admin.roles.delete' => '/admin/roles/{key}/delete',
            'admin.profile' => '/admin/profile',
            'admin.profile.update' => '/admin/profile',
            'admin.settings.system' => '/admin/settings/system',
            'admin.settings.system.save' => '/admin/settings/system',
            'admin.languages.index' => '/admin/settings/languages',
            'admin.languages.create' => '/admin/settings/languages/new',
            'admin.languages.edit' => '/admin/settings/languages/{code}/edit',
            'admin.languages.delete' => '/admin/settings/languages/{code}/delete',
            'admin.cache.index' => '/admin/cache',
            'admin.cache.clear' => '/admin/cache/clear',
            'admin.logs.index' => '/admin/logs',
            'admin.logs.clear' => '/admin/logs/clear',
        ]);
    }

    /** @param array<string, string> $routes */
    public static function with(array $routes): UrlGenerator
    {
        $router = new Router();
        foreach ($routes as $name => $path) {
            $router->get($path, static fn (): null => null)->name($name);
        }

        return new UrlGenerator($router);
    }
}
