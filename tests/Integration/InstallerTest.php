<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Installer\InstallationInput;
use Jinstan\Core\Application\Installer\Services\InstallApplicationService;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Infrastructure\Filesystem\Installer\InstallationLock;
use Jinstan\Core\Infrastructure\Filesystem\Installer\DatabaseConfigWriter;
use Jinstan\Core\Infrastructure\Persistence\Installer\InstallationDatabaseInstaller;
use Jinstan\Core\Infrastructure\Persistence\Installer\InstallationSeeder;
use Jinstan\Core\Infrastructure\System\EnvironmentChecker;
use Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager;
use Jinstan\Core\Infrastructure\Database\DatabaseOperationsAdapter;
use Jinstan\Core\Http\Controllers\Admin\DashboardController;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Application\Dashboard\Queries\DashboardQuery;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Http\Controllers\Admin\SettingsAdminController;
use Jinstan\Core\Application\Settings\Services\SaveSystemSettings;
use Jinstan\Core\Application\Theme\Queries\ThemeManagerQuery;
use Jinstan\Core\Application\Theme\Services\ThemeWriter;
use Jinstan\Core\Http\Controllers\Admin\ThemeAdminController;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\View\Presenters\Frontend\SeoRenderer;
use Jinstan\Core\View\Presenters\Admin\DashboardPresenter;
use Jinstan\Core\View\Presenters\Admin\SystemSettingsPresenter;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Http\Requests\Admin\SystemSettingsRequest;
use Jinstan\Tests\Support\TestUrlGenerator;
use Latte\Engine;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    public function testEnvironmentRequirementsPassInTestRuntime(): void
    {
        self::assertTrue((new EnvironmentChecker())->passes());
    }

    public function testInstallerRejectsASecondInstallationWithoutChangingExistingData(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-reinstall-' . bin2hex(random_bytes(6));
        mkdir($projectPath . '/storage/database', 0750, true);
        $installer = $this->installer($projectPath, new InstallationLock($projectPath . '/storage/installed.php'));
        $installer->execute([
            'site_name' => 'Original Site',
            'email' => 'admin@example.test',
            'password' => 'secure-password',
            'database_driver' => 'sqlite',
        ]);

        try {
            $installer->execute([
                'site_name' => 'Replaced Site',
                'email' => 'attacker@example.test',
                'password' => 'another-password',
                'database_driver' => 'sqlite',
            ]);
            self::fail('A completed installation must reject installer re-entry.');
        } catch (\RuntimeException $exception) {
            self::assertSame('安装已完成。', $exception->getMessage());
        }

        $connection = (new ConnectionFactory())->create([
            'driver' => 'sqlite',
            'path' => $projectPath . '/storage/database/database.sqlite',
        ]);
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM users'));
        self::assertSame('admin@example.test', $connection->fetchOne('SELECT email FROM users'));
        self::assertSame(
            'Original Site',
            $connection->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site.name'"),
        );
    }

    public function testInstallerCreatesDatabaseAdminSettingsAndLock(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-' . bin2hex(random_bytes(6));
        mkdir($projectPath . '/storage/database', 0750, true);

        $lock = new InstallationLock($projectPath . '/storage/installed.php');
        $installer = $this->installer($projectPath, $lock);
        $installer->execute([
            'site_name' => '安装测试站',
            'email' => 'admin@example.test',
            'password' => 'secure-password',
            'database_driver' => 'sqlite',
        ]);

        $connection = (new ConnectionFactory())->create([
            'driver' => 'sqlite',
            'path' => $projectPath . '/storage/database/database.sqlite',
        ]);

        self::assertTrue($lock->exists());
        self::assertSame('admin@example.test', $connection->fetchOne('SELECT email FROM users'));
        self::assertSame('安装测试站', $connection->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site.name'"));
        self::assertFileExists($projectPath . '/storage/config/database.php');
        self::assertSame(0600, fileperms($projectPath . '/storage/config/database.php') & 0777);
        self::assertSame(0600, fileperms($projectPath . '/storage/installed.php') & 0777);

        $_SESSION = [];
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
        self::assertTrue($auth->attempt('admin@example.test', 'secure-password'));
        self::assertNotNull($auth->userId());
        self::assertTrue($auth->can('content.manage'));
        self::assertTrue($auth->can('settings.manage'));

        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $types->register(new ContentTypeDefinition('example', '示例内容', [], 'content-detail', '/example/{slug}'));
        $dashboardViews = new AdminViewRenderer(
            dirname(__DIR__, 2) . '/core/resources/views/admin',
            $projectPath . '/storage/cache/latte-admin',
            TestUrlGenerator::adminViews(),
            contentTypes: $types,
        );
        $dashboardUrls = TestUrlGenerator::contentAdmin();
        $dashboard = new DashboardController(
            new DashboardQuery(
                new ContentRepository($connection),
                new DatabaseOperationsAdapter($connection),
                $types,
            ),
            new DashboardPresenter($dashboardViews, $dashboardUrls),
            $dashboardViews,
            new \Jinstan\Core\Http\ResponseFactory(),
        );
        $response = $dashboard->show(new ServerRequest('GET', '/admin'), []);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('后台概览', (string) $response->getBody());
        self::assertStringContainsString('示例内容', (string) $response->getBody());
        self::assertStringContainsString('href="/admin/content/example"', (string) $response->getBody());
        self::assertStringContainsString('style="--dashboard-columns: 4"', (string) $response->getBody());
        self::assertStringContainsString('class="mt-5 h-48"', (string) $response->getBody());
        self::assertStringContainsString('class="mx-auto mt-5 max-w-sm" style="height: 150px"', (string) $response->getBody());
        self::assertStringContainsString('系统信息', (string) $response->getBody());
        self::assertStringContainsString('数据库信息', (string) $response->getBody());
        self::assertStringContainsString('由 Jinstan v1.0.0 提供支持', (string) $response->getBody());
        self::assertStringNotContainsString('href="/admin/system"', (string) $response->getBody());
        self::assertStringContainsString('/assets/core/1.0.0/alpine.min.js', (string) $response->getBody());
        self::assertStringContainsString('/assets/core/1.0.0/admin.css', (string) $response->getBody());
        self::assertStringContainsString('/assets/core/1.0.0/codeflask.min.js', (string) $response->getBody());
        self::assertStringContainsString('/assets/core/1.0.0/chart.js/chart.umd.min.js', (string) $response->getBody());
        self::assertStringContainsString('第三方库版本', (string) $response->getBody());
        self::assertStringContainsString('href="https://latte.nette.org/"', (string) $response->getBody());
        self::assertStringContainsString('href="https://commonmark.thephpleague.com/"', (string) $response->getBody());
        self::assertStringContainsString('href="https://github.com/nikic/FastRoute"', (string) $response->getBody());
        self::assertStringContainsString('href="https://github.com/Nyholm/psr7"', (string) $response->getBody());
        self::assertStringContainsString('href="https://github.com/Nyholm/psr7-server"', (string) $response->getBody());
        self::assertStringContainsString('href="https://www.php-fig.org/psr/psr-11/"', (string) $response->getBody());
        self::assertStringContainsString('href="https://www.php-fig.org/psr/psr-7/"', (string) $response->getBody());
        self::assertStringContainsString('href="https://www.chartjs.org/"', (string) $response->getBody());
        self::assertSame(8, substr_count((string) $response->getBody(), 'rel="noopener noreferrer"'));
        self::assertStringNotContainsString('新建文章', (string) $response->getBody());
        self::assertStringNotContainsString('存储目录可写', (string) $response->getBody());
        self::assertStringNotContainsString('monolog/monolog', (string) $response->getBody());
        self::assertStringNotContainsString('快捷操作', (string) $response->getBody());
        self::assertStringNotContainsString('cdn.', (string) $response->getBody());
        self::assertStringContainsString('--toss-blue', (string) $response->getBody());
        self::assertStringNotContainsString('后台主题', (string) $response->getBody());
    }

    public function testSystemAndSeoSettingsCanBeSavedAndUsedForSeoTags(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-settings-' . bin2hex(random_bytes(6));
        mkdir($projectPath . '/storage/database', 0750, true);
        $lock = new InstallationLock($projectPath . '/storage/installed.php');
        $installer = $this->installer($projectPath, $lock);
        $installer->execute(['site_name' => '旧名称', 'email' => 'admin@example.test', 'password' => 'secure-password', 'database_driver' => 'sqlite']);

        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $projectPath . '/storage/database/database.sqlite']);
        $_SESSION = [];
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
        self::assertTrue($auth->attempt('admin@example.test', 'secure-password'));

        $csrf = new CsrfToken();
        $settings = new SettingsRepository($connection);
        $contents = new ContentRepository($connection);
        $settingsRequest = new SystemSettingsRequest(
            $settings,
            new \Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager(dirname(__DIR__, 2) . '/themes'),
            $contents,
        );
        $settingsViews = new AdminViewRenderer(
            dirname(__DIR__, 2) . '/core/resources/views/admin',
            $projectPath . '/storage/cache/latte-admin',
            TestUrlGenerator::adminViews(),
        );
        $controller = new SettingsAdminController(
            $csrf,
            new SystemSettingsPresenter($contents, $settings, $settingsRequest),
            new SaveSystemSettings($settings),
            $settingsViews,
            TestUrlGenerator::adminDestinations(),
            $settingsRequest,
            new \Jinstan\Core\Http\ResponseFactory(),
        );
        $system = new ServerRequest('POST', 'https://example.test/admin/settings/system');
        $system = $system->withParsedBody([
            '_token' => $csrf->value(),
            'site_name' => '示例站点',
            'site_tagline' => '轻量 CMS',
            'site_description' => '站点默认描述',
            'contact_email' => 'hello@example.test',
            'site_footer' => '© 示例站点 版权所有',
            'frontend_locale' => 'zh-CN',
            'admin_locale' => 'ko',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y年m月d日',
            'theme' => 'default',
            'default_title' => '示例站点',
            'title_separator' => ' · ',
            'title_homepage' => '{site_name}{separator}{tagline}',
            'title_content' => '{title}{separator}{site_name}',
            'title_listing' => '{content_type}{page_suffix}{separator}{site_name}',
            'title_not_found' => '{title}{separator}{site_name}',
            'default_description' => '',
            'robots' => 'noindex,follow',
            'sns' => [
                'weibo' => 'https://weibo.com/example',
                'naver_blog' => 'https://blog.naver.com/example',
            ],
            'custom_keys' => ['company.name', 'support_phone'],
            'custom_values' => ['示例公司', '400-000-0000'],
        ]);
        self::assertSame(303, $controller->saveSystem($system, [])->getStatusCode());
        self::assertSame('https://example.test', $settings->get('site.url'));
        self::assertSame('zh-CN', $settings->get('site.default_locale'));
        self::assertSame('ko', $settings->get('admin.locale'));
        $unsupportedAdminLocale = $system->withParsedBody([
            ...$system->getParsedBody(),
            'admin_locale' => 'en',
        ]);
        self::assertSame(422, $controller->saveSystem($unsupportedAdminLocale, [])->getStatusCode());
        self::assertSame('ko', $settings->get('admin.locale'));
        self::assertSame('default', $settings->get('site.theme'));
        self::assertSame('轻量 CMS', $settings->get('site.tagline'));
        self::assertSame('© 示例站点 版权所有', $settings->get('site.footer'));
        self::assertSame('Y年m月d日', $settings->get('site.date_format'));
        self::assertSame('https://weibo.com/example', $settings->get('site.sns.weibo'));
        self::assertSame('https://blog.naver.com/example', $settings->get('site.sns.naver_blog'));
        self::assertSame(['company.name' => '示例公司', 'support_phone' => '400-000-0000'], $settings->custom());
        self::assertStringContainsString('语言与地区', (string) $controller->system(new ServerRequest('GET', '/admin/settings/system'), [])->getBody());
        self::assertStringContainsString('前台主题', (string) $controller->system(new ServerRequest('GET', '/admin/settings/system'), [])->getBody());
        self::assertStringContainsString('company.name', (string) $controller->system((new ServerRequest('GET', '/admin/settings/system?tab=custom'))->withQueryParams(['tab' => 'custom']), [])->getBody());
        $seoTab = (string) $controller->system((new ServerRequest('GET', '/admin/settings/system?tab=seo'))->withQueryParams(['tab' => 'seo']), [])->getBody();
        self::assertStringContainsString('标题格式', $seoTab);
        self::assertStringNotContainsString('社交分享', $seoTab);
        self::assertStringNotContainsString('站长验证', $seoTab);
        $tags = (new SeoRenderer($settings))->tags(new ContentTranslation('en', 'Guide', 'guide', null, []), '/guide');
        self::assertSame(
            'Guide · 示例站点',
            (new SeoRenderer($settings))->title(new ContentTranslation('en', 'Guide', 'guide', null, [])),
        );
        self::assertStringContainsString('站点默认描述', $tags);
        self::assertStringContainsString('noindex,follow', $tags);
        self::assertStringNotContainsString('og:', $tags);
        self::assertStringNotContainsString('twitter:', $tags);
        $seoRenderer = new SeoRenderer($settings);
        self::assertSame('示例站点 · 轻量 CMS', $seoRenderer->siteTitle());
        self::assertSame('文章 · 2 · 示例站点', $seoRenderer->title(new ContentTranslation('zh-CN', '文章', 'blog', null, []), true, 'listing', '文章', 2));
    }

    public function testThemeTemplatesCanBeEditedFromTheAdmin(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-themes-' . bin2hex(random_bytes(6));
        mkdir($projectPath . '/storage/database', 0750, true);
        mkdir($projectPath . '/themes/default/layouts', 0750, true);
        mkdir($projectPath . '/themes/alternate/templates', 0750, true);
        file_put_contents($projectPath . '/themes/default/layouts/default.jst', '<main>初始内容</main>');
        file_put_contents($projectPath . '/themes/alternate/theme.json', '{"name":"Alternate"}');
        file_put_contents($projectPath . '/themes/alternate/templates/page.jst', '<article>备用主题</article>');

        $lock = new InstallationLock($projectPath . '/storage/installed.php');
        $installer = $this->installer($projectPath, $lock);
        $installer->execute(['site_name' => '主题测试站', 'email' => 'admin@example.test', 'password' => 'secure-password', 'database_driver' => 'sqlite']);

        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $projectPath . '/storage/database/database.sqlite']);
        $_SESSION = [];
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
        self::assertTrue($auth->attempt('admin@example.test', 'secure-password'));

        $csrf = new CsrfToken();
        $themeFiles = new ThemeFileManager($projectPath . '/themes');
        $themeSettings = new SettingsRepository($connection);
        $controller = new ThemeAdminController(
            $csrf,
            new ThemeManagerQuery($themeFiles, $themeSettings),
            new ThemeWriter($themeFiles, $themeSettings),
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                $projectPath . '/storage/cache/latte-admin',
                TestUrlGenerator::adminViews(),
            ),
            TestUrlGenerator::adminDestinations(),
            new \Jinstan\Core\Http\ResponseFactory(),
        );
        $show = $controller->show(new ServerRequest('GET', '/admin/themes?theme=default'), []);
        self::assertSame(200, $show->getStatusCode());
        self::assertStringContainsString('alternate', (string) $show->getBody());
        self::assertStringContainsString('layouts', (string) $show->getBody());
        self::assertStringContainsString('default.jst', (string) $show->getBody());
        self::assertStringContainsString('id="theme-file-tree"', (string) $show->getBody());
        self::assertStringContainsString('role="tree"', (string) $show->getBody());
        self::assertStringContainsString('toggleDirectory', (string) $show->getBody());
        self::assertStringContainsString('data-tree-depth="1"', (string) $show->getBody());
        self::assertStringContainsString('class="theme-create-modal"', (string) $show->getBody());
        self::assertGreaterThanOrEqual(4, substr_count((string) $show->getBody(), 'class="theme-create-modal"'));
        self::assertStringContainsString('创建后将自动生成 theme.json、基础目录和默认 .jst 模板。', (string) $show->getBody());
        self::assertStringNotContainsString('class="mt-3 code-editor" data-code-editor', (string) $show->getBody());

        $selected = $controller->show(new ServerRequest('GET', '/admin/themes?theme=default&file=layouts%2Fdefault.jst'), []);
        self::assertStringContainsString('class="mt-3 code-editor" data-code-editor', (string) $selected->getBody());
        self::assertStringNotContainsString('action="/admin/themes/files/delete"', (string) $selected->getBody());
        self::assertStringNotContainsString('action="/admin/themes/directories/delete"', (string) $selected->getBody());

        $deleteDefaultFile = (new ServerRequest('POST', '/admin/themes/files/delete'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'default', 'file' => 'layouts/default.jst', 'confirm' => 'layouts/default.jst',
        ]);
        self::assertSame(422, $controller->deleteFile($deleteDefaultFile, [])->getStatusCode());
        self::assertFileExists($projectPath . '/themes/default/layouts/default.jst');

        $deleteDefaultDirectory = (new ServerRequest('POST', '/admin/themes/directories/delete'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'default', 'directory' => 'layouts', 'confirm' => 'layouts',
        ]);
        self::assertSame(422, $controller->deleteDirectory($deleteDefaultDirectory, [])->getStatusCode());
        self::assertDirectoryExists($projectPath . '/themes/default/layouts');

        $renameDefaultDirectory = (new ServerRequest('POST', '/admin/themes/directories/rename'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'default', 'directory' => 'layouts', 'name' => 'renamed-layouts',
        ]);
        self::assertSame(422, $controller->renameDirectory($renameDefaultDirectory, [])->getStatusCode());
        self::assertDirectoryExists($projectPath . '/themes/default/layouts');
        self::assertDirectoryDoesNotExist($projectPath . '/themes/default/renamed-layouts');

        $alternate = $controller->show(new ServerRequest('GET', '/admin/themes?theme=alternate'), []);
        self::assertSame(200, $alternate->getStatusCode());
        self::assertStringContainsString('templates', (string) $alternate->getBody());
        self::assertStringContainsString('page.jst', (string) $alternate->getBody());

        $deleteManifest = (new ServerRequest('POST', '/admin/themes/files/delete'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'file' => 'theme.json', 'confirm' => 'theme.json',
        ]);
        self::assertSame(422, $controller->deleteFile($deleteManifest, [])->getStatusCode());
        self::assertFileExists($projectPath . '/themes/alternate/theme.json');

        $save = (new ServerRequest('POST', '/admin/themes'))->withParsedBody([
            '_token' => $csrf->value(),
            'theme' => 'default',
            'file' => 'layouts/default.jst',
            'source' => '<main>更新后的内容</main>',
            'confirm_overwrite' => '1',
        ]);
        self::assertSame(303, $controller->save($save, [])->getStatusCode());
        self::assertSame('<main>更新后的内容</main>', file_get_contents($projectPath . '/themes/default/layouts/default.jst'));

        $invalid = (new ServerRequest('POST', '/admin/themes'))->withParsedBody([
            '_token' => $csrf->value(),
            'theme' => 'default',
            'file' => '../outside.jst',
            'source' => 'unsafe',
            'confirm_overwrite' => '1',
        ]);
        self::assertSame(422, $controller->save($invalid, [])->getStatusCode());

        $createDirectory = (new ServerRequest('POST', '/admin/themes/directories/create'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'parent' => 'templates', 'name' => 'landing',
        ]);
        self::assertSame(303, $controller->createDirectory($createDirectory, [])->getStatusCode());
        self::assertDirectoryExists($projectPath . '/themes/alternate/templates/landing');

        $createFile = (new ServerRequest('POST', '/admin/themes/files/create'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'directory' => 'templates/landing', 'name' => 'offer.jst',
        ]);
        self::assertSame(303, $controller->createFile($createFile, [])->getStatusCode());
        self::assertFileExists($projectPath . '/themes/alternate/templates/landing/offer.jst');
        $showCreated = $controller->show(new ServerRequest('GET', '/admin/themes?theme=alternate&file=templates%2Flanding%2Foffer.jst'), []);
        self::assertStringContainsString('offer.jst', (string) $showCreated->getBody());

        $nonEmptyDirectory = (new ServerRequest('POST', '/admin/themes/directories/delete'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'directory' => 'templates/landing', 'confirm' => 'templates/landing',
        ]);
        self::assertSame(422, $controller->deleteDirectory($nonEmptyDirectory, [])->getStatusCode());
        $deleteFile = (new ServerRequest('POST', '/admin/themes/files/delete'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'file' => 'templates/landing/offer.jst', 'confirm' => 'templates/landing/offer.jst',
        ]);
        self::assertSame(303, $controller->deleteFile($deleteFile, [])->getStatusCode());
        self::assertSame(303, $controller->deleteDirectory($nonEmptyDirectory, [])->getStatusCode());
        self::assertDirectoryDoesNotExist($projectPath . '/themes/alternate/templates/landing');

        $renameSource = (new ServerRequest('POST', '/admin/themes/directories/create'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'parent' => 'templates', 'name' => 'before-rename',
        ]);
        self::assertSame(303, $controller->createDirectory($renameSource, [])->getStatusCode());
        $renameDirectory = (new ServerRequest('POST', '/admin/themes/directories/rename'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'directory' => 'templates/before-rename', 'name' => 'after-rename',
        ]);
        self::assertSame(303, $controller->renameDirectory($renameDirectory, [])->getStatusCode());
        self::assertDirectoryDoesNotExist($projectPath . '/themes/alternate/templates/before-rename');
        self::assertDirectoryExists($projectPath . '/themes/alternate/templates/after-rename');

        $invalidType = (new ServerRequest('POST', '/admin/themes/files/create'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'directory' => '', 'name' => 'unsafe.php',
        ]);
        self::assertSame(422, $controller->createFile($invalidType, [])->getStatusCode());
        self::assertFileDoesNotExist($projectPath . '/themes/alternate/unsafe.php');

        $nonTemplateType = (new ServerRequest('POST', '/admin/themes/files/create'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'alternate', 'directory' => '', 'name' => 'custom.css',
        ]);
        self::assertSame(422, $controller->createFile($nonTemplateType, [])->getStatusCode());
        self::assertFileDoesNotExist($projectPath . '/themes/alternate/custom.css');

        $createTheme = (new ServerRequest('POST', '/admin/themes/create'))->withParsedBody([
            '_token' => $csrf->value(), 'handle' => 'campaign', 'name' => 'Campaign',
        ]);
        self::assertSame(303, $controller->createTheme($createTheme, [])->getStatusCode());
        self::assertFileExists($projectPath . '/themes/campaign/theme.json');
        self::assertFileExists($projectPath . '/themes/campaign/templates/content-detail.jst');
        $latte = new Engine();
        foreach (['layouts/default.jst', 'templates/homepage.jst', 'templates/content-detail.jst', 'templates/content-list.jst'] as $template) {
            self::assertNotSame('', $latte->compile($projectPath . '/themes/campaign/' . $template));
        }
        $deleteTheme = (new ServerRequest('POST', '/admin/themes/delete'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'campaign', 'confirm' => 'campaign',
        ]);
        self::assertSame(303, $controller->deleteTheme($deleteTheme, [])->getStatusCode());
        self::assertDirectoryDoesNotExist($projectPath . '/themes/campaign');

        $deleteActiveTheme = (new ServerRequest('POST', '/admin/themes/delete'))->withParsedBody([
            '_token' => $csrf->value(), 'theme' => 'default', 'confirm' => 'default',
        ]);
        self::assertSame(422, $controller->deleteTheme($deleteActiveTheme, [])->getStatusCode());

    }

    private function installer(string $projectPath, InstallationLock $lock): InstallApplicationService
    {
        return new InstallApplicationService(
            new InstallationDatabaseInstaller(
                new ConnectionFactory(),
                new MigrationRunner(),
                new InstallationSeeder(),
                dirname(__DIR__, 2) . '/core/src/Database/Migrations',
            ),
            $lock,
            new InstallationInput($projectPath),
            new DatabaseConfigWriter($projectPath),
        );
    }
}
