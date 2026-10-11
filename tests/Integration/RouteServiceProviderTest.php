<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Http\Application as HttpApplication;
use Jinstan\Core\Http\Middleware\AuthenticateMiddleware;
use Jinstan\Core\Http\Middleware\AdminLocaleMiddleware;
use Jinstan\Core\Http\Middleware\EnsureNotInstalledMiddleware;
use Jinstan\Core\Http\Middleware\MaintenanceModeMiddleware;
use Jinstan\Core\Http\Middleware\RequirePermissionMiddleware;
use Jinstan\Core\Http\Middleware\VerifyCsrfTokenMiddleware;
use Jinstan\Core\I18n\AdminLocale;
use Jinstan\Core\Providers\AdminServiceProvider;
use Jinstan\Core\Providers\ContentServiceProvider;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Providers\InstallerServiceProvider;
use Jinstan\Core\Providers\PluginServiceProvider;
use Jinstan\Core\Providers\RouteServiceProvider;
use Jinstan\Core\Providers\ThemeServiceProvider;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Filesystem\Update\MaintenanceMode;
use Jinstan\Tests\Support\InstalledTestProject;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RouteServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testItBuildsHttpApplicationForUninstalledProject(): void
    {
        $application = $this->application(InstalledTestProject::create(false));
        $http = $application->container()->get(HttpApplication::class);

        self::assertSame(200, $http->handle(new ServerRequest('GET', '/install'))->getStatusCode());
        self::assertSame(404, $http->handle(new ServerRequest('GET', '/login'))->getStatusCode());
        $router = $application->container()->get(Router::class);
        self::assertInstanceOf(EnsureNotInstalledMiddleware::class, $router->named('install.show')?->middlewareStack()[0] ?? null);
        self::assertInstanceOf(EnsureNotInstalledMiddleware::class, $router->named('install.store')?->middlewareStack()[0] ?? null);
    }

    public function testItBuildsInstalledRoutesAroundPluginBoot(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $http = $application->container()->get(HttpApplication::class);

        self::assertSame(200, $http->handle(new ServerRequest('GET', '/'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/hello-plugin'))->getStatusCode());
        self::assertSame(303, $http->handle(new ServerRequest('GET', '/admin'))->getStatusCode());
        $urls = $application->container()->get(UrlGenerator::class);
        self::assertSame('/login', $urls->route('login.show'));
        self::assertSame('/admin', $urls->route('admin.dashboard'));
        self::assertSame('/admin/settings/system?tab=predefined', $urls->route('admin.settings.system', ['tab' => 'predefined']));
        self::assertSame('/admin/database', $urls->route('admin.database.index'));
        self::assertSame('/admin/cache', $urls->route('admin.cache.index'));
        $router = $application->container()->get(Router::class);
        self::assertCount(2, $router->named('admin.dashboard')?->middlewareStack() ?? []);
        self::assertInstanceOf(AuthenticateMiddleware::class, $router->named('admin.dashboard')?->middlewareStack()[0] ?? null);
        self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $router->named('admin.dashboard')?->middlewareStack()[1] ?? null);
        self::assertCount(3, $router->named('admin.content-types.index')?->middlewareStack() ?? []);
        self::assertInstanceOf(AuthenticateMiddleware::class, $router->named('admin.content-types.index')?->middlewareStack()[0] ?? null);
        self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $router->named('admin.content-types.index')?->middlewareStack()[1] ?? null);
        self::assertInstanceOf(RequirePermissionMiddleware::class, $router->named('admin.content-types.index')?->middlewareStack()[2] ?? null);
        $mediaMiddleware = $router->named('admin.media.show')?->middlewareStack() ?? [];
        self::assertCount(3, $mediaMiddleware);
        self::assertInstanceOf(AuthenticateMiddleware::class, $mediaMiddleware[0]);
        self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $mediaMiddleware[1]);
        self::assertInstanceOf(RequirePermissionMiddleware::class, $mediaMiddleware[2]);
        foreach ([
            'admin.content.terms.search',
            'admin.content.index',
            'admin.content.create',
            'admin.content.parents.search',
            'admin.content.store',
            'admin.content.edit',
            'admin.content.update',
            'admin.content.delete',
        ] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach (['admin.cache.index', 'admin.cache.clear', 'admin.logs.index', 'admin.logs.clear'] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach ([
            'admin.users.index',
            'admin.users.create',
            'admin.users.store',
            'admin.users.edit',
            'admin.users.update',
            'admin.users.delete',
            'admin.roles.index',
            'admin.roles.create',
            'admin.roles.store',
            'admin.roles.edit',
            'admin.roles.update',
            'admin.roles.delete',
        ] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach ([
            'admin.taxonomies.index',
            'admin.taxonomies.create',
            'admin.taxonomies.store',
            'admin.taxonomies.edit',
            'admin.taxonomies.update',
            'admin.taxonomies.delete.confirm',
            'admin.taxonomies.delete',
            'admin.taxonomy-terms.create',
            'admin.taxonomy-terms.store',
            'admin.taxonomy-terms.edit',
            'admin.taxonomy-terms.update',
            'admin.taxonomy-terms.delete.confirm',
            'admin.taxonomy-terms.delete',
            'admin.taxonomy-terms.reorder',
        ] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach (['admin.database.index', 'admin.database.check', 'admin.database.optimize', 'admin.database.backup', 'admin.database.backups.delete'] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach ([
            'admin.navigation.index',
            'admin.navigation.store',
            'admin.navigation-items.create',
            'admin.navigation-items.store',
            'admin.navigation-items.reorder',
            'admin.navigation-items.update',
            'admin.navigation-items.edit',
            'admin.navigation-items.delete',
        ] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach (['admin.plugins.index', 'admin.plugins.settings', 'admin.plugins.toggle', 'admin.plugins.settings.save', 'admin.plugins.uninstall'] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach ([
            'admin.themes.index',
            'admin.themes.save',
            'admin.themes.create',
            'admin.themes.delete',
            'admin.themes.directories.create',
            'admin.themes.directories.rename',
            'admin.themes.directories.delete',
            'admin.themes.files.create',
            'admin.themes.files.delete',
        ] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
        foreach (['admin.profile', 'admin.profile.update'] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(2, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
        }
        foreach ([
            'admin.settings.system',
            'admin.settings.system.save',
            'admin.languages.index',
            'admin.languages.create',
            'admin.languages.store',
            'admin.languages.edit',
            'admin.languages.update',
            'admin.languages.delete',
        ] as $name) {
            $middleware = $router->named($name)?->middlewareStack() ?? [];
            self::assertCount(3, $middleware);
            self::assertInstanceOf(AuthenticateMiddleware::class, $middleware[0]);
            self::assertInstanceOf(VerifyCsrfTokenMiddleware::class, $middleware[1]);
            self::assertInstanceOf(RequirePermissionMiddleware::class, $middleware[2]);
        }
    }

    public function testAdminLocaleIsResolvedAtRequestStartAndFallsBackSafely(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        self::assertInstanceOf(AdminLocaleMiddleware::class, $application->container()->get(AdminLocaleMiddleware::class));
        $http = $application->container()->get(HttpApplication::class);
        $settings = $application->container()->get(SettingsRepository::class);
        $locale = $application->container()->get(AdminLocale::class);

        $settings->set('admin.locale', 'ko');
        $korean = (string) $http->handle(new ServerRequest('GET', '/login'))->getBody();
        self::assertSame('ko', $locale->current());
        self::assertStringContainsString('관리자 로그인', $korean);

        $settings->set('admin.locale', '../missing');
        $fallback = (string) $http->handle(new ServerRequest('GET', '/login'))->getBody();
        self::assertSame('zh-CN', $locale->current());
        self::assertStringContainsString('管理员登录', $fallback);
        self::assertStringNotContainsString('관리자 로그인', $fallback);
    }

    public function testMaintenanceModeShortCircuitsTheHttpPipeline(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $container = $application->container();
        $http = $container->get(HttpApplication::class);
        $maintenance = $container->get(MaintenanceMode::class);

        self::assertInstanceOf(MaintenanceModeMiddleware::class, $container->get(MaintenanceModeMiddleware::class));

        try {
            $maintenance->enable();
            $response = $http->handle(new ServerRequest('GET', '/'));

            self::assertSame(503, $response->getStatusCode());
            self::assertSame('Service Unavailable', (string) $response->getBody());
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('60', $response->getHeaderLine('Retry-After'));
            self::assertSame(200, $http->handle(new ServerRequest('GET', '/health'))->getStatusCode());
            self::assertSame(
                'Jinstan is running.',
                (string) $http->handle(new ServerRequest('GET', '/health'))->getBody(),
            );
        } finally {
            $maintenance->disable();
        }

        self::assertSame(200, $http->handle(new ServerRequest('GET', '/'))->getStatusCode());
    }

    public function testLoginValidationReturnsStructuredJsonErrors(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $container = $application->container();
        $request = (new ServerRequest('POST', '/login'))
            ->withHeader('Accept', 'application/json')
            ->withParsedBody([
                '_token' => $container->get(CsrfToken::class)->value(),
                'email' => 'invalid',
                'password' => '',
            ]);

        $response = $container->get(HttpApplication::class)->handle($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'message' => '表单验证失败。',
            'errors' => [
                'email' => ['邮箱格式无效。'],
                'password' => ['请输入密码。'],
            ],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testCacheAndLogsRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/cache'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/cache'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/cache/clear'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/cache'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/cache/clear'))
            ->withParsedBody(['_token' => $application->container()->get(CsrfToken::class)->value()]);
        self::assertSame('/admin/cache?_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
    }

    public function testRolesRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/roles'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'role-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('role-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/roles'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/roles/new'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/roles'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/roles/new'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'role_key' => 'editor',
            'label' => '编辑',
            'permissions' => ['content.manage'],
        ]);
        self::assertSame('/admin/roles?_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
        self::assertNotFalse($connection->fetchAssociative('SELECT role_key FROM roles WHERE role_key = ?', ['editor']));

        $invalid = (new ServerRequest('POST', '/admin/roles/new'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'role_key' => 'Invalid Role',
            'label' => ' 待修正 ',
            'permissions' => ['content.manage', ['system.manage']],
        ]);
        $invalidResponse = $http->handle($invalid);
        self::assertSame(422, $invalidResponse->getStatusCode());
        self::assertStringContainsString('value="Invalid Role"', (string) $invalidResponse->getBody());
        self::assertStringContainsString('value="待修正"', (string) $invalidResponse->getBody());
        self::assertFalse($connection->fetchOne('SELECT role_key FROM roles WHERE role_key = ?', ['Invalid Role']));
    }

    public function testUsersRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/users'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'user-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('user-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/users'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/users/new'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/users'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/users/new'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'email' => 'editor@example.test',
            'password' => 'editor-password',
            'roles' => ['administrator'],
        ]);
        self::assertSame('/admin/users?_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
        self::assertNotFalse($connection->fetchAssociative('SELECT id FROM users WHERE email = ?', ['editor@example.test']));

        $invalid = (new ServerRequest('POST', '/admin/users/new'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'email' => ' invalid ',
            'password' => 'short',
            'roles' => [['administrator']],
        ]);
        $invalidResponse = $http->handle($invalid);
        self::assertSame(422, $invalidResponse->getStatusCode());
        self::assertStringContainsString('value="invalid"', (string) $invalidResponse->getBody());
        self::assertFalse($connection->fetchOne('SELECT id FROM users WHERE email = ?', ['invalid']));

        $invalidProfile = (new ServerRequest('POST', '/admin/profile'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'email' => 'not-an-email',
            'password' => '',
        ]);
        $profileResponse = $http->handle($invalidProfile);
        self::assertSame(422, $profileResponse->getStatusCode());
        self::assertStringContainsString('value="not-an-email"', (string) $profileResponse->getBody());
        self::assertSame($project->adminEmail(), $connection->fetchOne('SELECT email FROM users WHERE id = 1'));
    }

    public function testLanguagesRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/settings/languages'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'language-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('language-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/settings/languages'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/settings/languages/new'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/settings/languages'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/settings/languages/new'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'code' => 'ja',
            'name' => '日本語',
            'direction' => 'ltr',
        ]);
        self::assertSame('/admin/settings/languages?_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
        $locales = (string) $connection->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site.enabled_locales'");
        self::assertStringContainsString('ja', $locales);
    }

    public function testSystemSettingsRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/settings/system'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'settings-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('settings-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/settings/system'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/settings/system'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/settings/system'))->getStatusCode());
        $request = (new ServerRequest('POST', 'https://example.test/admin/settings/system'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'site_name' => '新站点名称',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'theme' => 'default',
            'active_tab' => 'site',
        ]);
        self::assertSame('/admin/settings/system?tab=site&_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
        self::assertSame('新站点名称', $connection->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site.name'"));

        $invalid = (new ServerRequest('POST', 'https://example.test/admin/settings/system'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'site_name' => '',
            'contact_email' => 'invalid',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'timezone' => 'Missing/Timezone',
            'date_format' => 'Y-m-d',
            'theme' => 'default',
        ]);
        $invalidResponse = $http->handle($invalid);
        self::assertSame(422, $invalidResponse->getStatusCode());
        self::assertSame('站点名称不能为空。', (string) $invalidResponse->getBody());
        self::assertSame('新站点名称', $connection->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site.name'"));

        $invalidCustom = (new ServerRequest('POST', 'https://example.test/admin/settings/system'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'site_name' => '不可保存的名称',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'theme' => 'default',
            'custom_keys' => ['company.name', ' company.name '],
            'custom_values' => ['first', 'second'],
        ]);
        $customResponse = $http->handle($invalidCustom);
        self::assertSame(422, $customResponse->getStatusCode());
        self::assertStringContainsString('自定义设置键不能重复', (string) $customResponse->getBody());
        self::assertSame('新站点名称', $connection->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site.name'"));
        self::assertFalse($connection->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'custom.company.name'"));
    }

    public function testPluginsRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/plugins'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'plugin-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('plugin-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/plugins'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/plugins/hello-plugin'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/plugins'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/plugins/hello-plugin'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'enabled' => '0',
        ]);
        self::assertSame('/admin/plugins?_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
        self::assertSame(0, (int) $connection->fetchOne("SELECT enabled FROM plugins WHERE name = 'hello-plugin'"));
    }

    public function testThemesRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/themes'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'theme-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('theme-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/themes'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/themes/delete'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/themes'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/themes/delete'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'theme' => 'default',
            'confirm' => 'default',
        ]);
        self::assertSame(422, $http->handle($request)->getStatusCode());
        self::assertDirectoryExists($project->path . '/themes/default');
    }

    public function testDatabaseToolsRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/database'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'database-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('database-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/database'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/database/check'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/database'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/database/check'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
        ]);
        self::assertSame('/admin/database?_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
    }

    public function testNavigationRequiresAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/navigation'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'navigation-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('navigation-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/navigation'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/navigation'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/navigation'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/navigation'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'handle' => 'footer',
            'name' => '页脚导航',
        ]);
        self::assertSame('/admin/navigation?locale=en&_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
        self::assertSame('页脚导航', $connection->fetchOne("SELECT name FROM navigation_menus WHERE handle = 'footer'"));
    }

    public function testTaxonomiesRequireAuthenticationPermissionAndCsrf(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/taxonomies'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'taxonomy-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('taxonomy-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/taxonomies'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/taxonomies/new'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/taxonomies'))->getStatusCode());
        $request = (new ServerRequest('POST', '/admin/taxonomies/new'))->withParsedBody([
            '_token' => $application->container()->get(CsrfToken::class)->value(),
            'handle' => 'audience',
            'label' => '受众',
        ]);
        self::assertSame('/admin/taxonomies?_feedback=saved', $http->handle($request)->getHeaderLine('Location'));
        self::assertSame('受众', $connection->fetchOne("SELECT label FROM taxonomies WHERE handle = 'audience'"));
    }

    public function testProtectedMediaRequiresAuthenticationAndContentPermission(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        $path = '/admin/media/aa/bb/' . str_repeat('0', 32) . '.jpg';
        self::assertSame('/login', $http->handle(new ServerRequest('GET', $path))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'media-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('media-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', $path))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(404, $http->handle(new ServerRequest('GET', $path))->getStatusCode());
    }

    public function testContentRoutesRequireContentPermission(): void
    {
        $project = InstalledTestProject::create();
        $application = $this->application($project);
        $http = $application->container()->get(HttpApplication::class);
        self::assertSame('/login', $http->handle(new ServerRequest('GET', '/admin/content/article'))->getHeaderLine('Location'));

        $connection = $project->connection();
        $connection->insert('roles', ['role_key' => 'viewer', 'label' => '只读']);
        $connection->insert('users', [
            'email' => 'content-viewer@example.test',
            'password_hash' => password_hash('viewer-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'viewer']);
        $auth = $application->container()->get(Authenticator::class);
        self::assertTrue($auth->attempt('content-viewer@example.test', 'viewer-password'));
        self::assertSame(403, $http->handle(new ServerRequest('GET', '/admin/content/article'))->getStatusCode());

        $_SESSION = [];
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));
        self::assertSame(419, $http->handle(new ServerRequest('POST', '/admin/content/article'))->getStatusCode());
        self::assertSame(200, $http->handle(new ServerRequest('GET', '/admin/content/article'))->getStatusCode());
    }

    private function application(InstalledTestProject $project): Application
    {
        $application = new Application($project->path, 'testing');
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->register(new InstallerServiceProvider());
        $application->register(new ContentServiceProvider());
        $application->register(new ThemeServiceProvider());
        $application->register(new AdminServiceProvider());
        $application->register(new PluginServiceProvider());
        $application->register(new RouteServiceProvider());
        $application->boot();

        return $application;
    }
}
