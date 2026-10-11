<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use Jinstan\Core\Bootstrap\Bootstrap;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Routing\Router;
use Jinstan\Tests\Support\InstalledTestProject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RouteInventoryTest extends TestCase
{
    public function testCoreAndOfficialPluginRoutesRemainRegistered(): void
    {
        $_SESSION = [];
        $application = Bootstrap::application(InstalledTestProject::create()->path);

        self::assertSame($this->expectedRoutes(), $this->registeredRoutes($application));
    }

    /** @return list<string> */
    private function registeredRoutes(Application $application): array
    {
        $router = $application->container()->get(Router::class);

        $routerReflection = new ReflectionClass($router);
        $routes = $routerReflection->getProperty('routes')->getValue($router);
        self::assertIsArray($routes);

        return array_keys($routes);
    }

    /** @return list<string> */
    private function expectedRoutes(): array
    {
        return array_values(array_filter(explode("\n", <<<'ROUTES'
GET /health
GET /
GET /install
POST /install
GET /{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}
GET /{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}/
GET /sitemap.xml
GET /login
POST /login
GET /media/{first:[a-f0-9]{2}}/{second:[a-f0-9]{2}}/{file:[a-f0-9]{32}\.(?:jpg|jpeg|png|webp|pdf)}
GET /admin/media/{first:[a-f0-9]{2}}/{second:[a-f0-9]{2}}/{file:[a-f0-9]{32}\.(?:jpg|jpeg|png|webp|pdf)}
GET /admin/content/{type}/terms/{group}
GET /admin
GET /admin/content/{type}
GET /admin/content/{type}/create
GET /admin/content/{type}/parents
POST /admin/content/{type}
GET /admin/content/{type}/{id}/edit
POST /admin/content/{type}/{id}
POST /admin/content/{type}/{id}/delete
GET /admin/content-types
GET /admin/content-types/{handle}
POST /admin/content-types
POST /admin/content-types/{handle}/delete
GET /admin/taxonomies
GET /admin/taxonomies/new
POST /admin/taxonomies/new
GET /admin/taxonomies/{handle}/edit
POST /admin/taxonomies/{handle}/edit
GET /admin/taxonomies/{handle}/delete
POST /admin/taxonomies/{handle}/delete
GET /admin/taxonomies/{handle}/terms/new
POST /admin/taxonomies/{handle}/terms/new
GET /admin/taxonomies/{handle}/terms/{id:\d+}/edit
POST /admin/taxonomies/{handle}/terms/{id:\d+}/edit
GET /admin/taxonomies/{handle}/terms/{id:\d+}/delete
POST /admin/taxonomies/{handle}/terms/{id:\d+}/delete
POST /admin/taxonomies/terms/reorder
GET /admin/navigation
POST /admin/navigation
GET /admin/navigation/items/new
POST /admin/navigation/items
POST /admin/navigation/items/reorder
POST /admin/navigation/items/{id}
GET /admin/navigation/items/{id}/edit
POST /admin/navigation/items/{id}/delete
POST /external-order
GET /hello-plugin
GET /admin/plugins
GET /admin/plugins/{name}/settings
POST /admin/plugins/{name}
POST /admin/plugins/{name}/settings
POST /admin/plugins/{name}/uninstall
GET /admin/themes
POST /admin/themes
POST /admin/themes/create
POST /admin/themes/delete
POST /admin/themes/directories/create
POST /admin/themes/directories/rename
POST /admin/themes/directories/delete
POST /admin/themes/files/create
POST /admin/themes/files/delete
GET /admin/database
POST /admin/database/check
POST /admin/database/optimize
POST /admin/database/cleanup
POST /admin/database/backup
POST /admin/database/backups/{id:\d{14}}/delete
GET /admin/users
GET /admin/users/new
POST /admin/users/new
GET /admin/users/{id:\d+}/edit
POST /admin/users/{id:\d+}/edit
POST /admin/users/{id:\d+}/delete
GET /admin/roles
GET /admin/roles/new
POST /admin/roles/new
GET /admin/roles/{key:[a-z][a-z0-9._-]+}/edit
POST /admin/roles/{key:[a-z][a-z0-9._-]+}/edit
POST /admin/roles/{key:[a-z][a-z0-9._-]+}/delete
GET /admin/profile
POST /admin/profile
POST /logout
GET /admin/settings/system
POST /admin/settings/system
GET /admin/settings/languages
GET /admin/settings/languages/new
POST /admin/settings/languages/new
GET /admin/settings/languages/{code:[A-Za-z0-9-]+}/edit
POST /admin/settings/languages/{code:[A-Za-z0-9-]+}/edit
POST /admin/settings/languages/{code:[A-Za-z0-9-]+}/delete
GET /admin/cache
POST /admin/cache/clear
GET /admin/logs
POST /admin/logs/clear
GET /blog
GET /products
GET /product/{slug}
GET /blog/{slug}
GET /{slug}
GET /{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}/products
GET /{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}/blog
GET /{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}/product/{slug}
GET /{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}/blog/{slug}
GET /{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}/{slug}
ROUTES)));
    }
}
