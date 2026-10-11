<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Filesystem\I18n\PhpTranslationCatalogLoader;
use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Http\Controllers\Admin\ContentAdminController;
use Jinstan\Core\Http\Controllers\Admin\NavigationAdminController;
use Jinstan\Core\View\Presenters\Admin\NavigationListPresenter;
use Jinstan\Core\Application\Navigation\Queries\NavigationItemFormQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationListQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationTranslationResolver;
use Jinstan\Core\Application\Navigation\Services\NavigationWriter;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\I18n\Translator;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use Jinstan\Tests\Support\ContentAdminControllerFactory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AdminListTranslationTest extends TestCase
{
    public function testListHeadingsAndBreadcrumbsFollowAdminLocale(): void
    {
        $root = dirname(__DIR__, 2);
        $cache = sys_get_temp_dir() . '/jinstan-list-i18n-' . bin2hex(random_bytes(6));
        $session = $_SESSION ?? [];
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($db, $root . '/core/src/Database/Migrations');
        $db->insert('users', ['email' => 'lists@example.test', 'password_hash' => 'unused', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $id = (int) $db->lastInsertId();
        $db->insert('user_roles', ['user_id' => $id, 'role_key' => 'administrator']);
        $_SESSION = ['_jinstan_user_id' => $id];
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $types->register(new ContentTypeDefinition('custom', '文章', [], 'content-detail', '/custom/{slug}'));
        $contents = new ContentRepository($db);
        $navigation = new NavigationRepository($db);
        $navigation->createMenu('primary', '导航菜单');
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($db));
        $csrf = new CsrfToken();
        $settings = new SettingsRepository($db);

        try {
            foreach (['zh-CN', 'ko'] as $locale) {
                $translator = new Translator(
                    $root . '/core/lang/admin',
                    static fn (): string => $locale,
                    loader: new PhpTranslationCatalogLoader(),
                );
                $views = new AdminViewRenderer($root . '/core/resources/views/admin', $cache, TestUrlGenerator::adminViews(), $csrf, $translator);
                $controller = ContentAdminControllerFactory::create(
                    $auth,
                    $types,
                    $contents,
                    new ContentService($types, $contents),
                    new ContentAttachmentStorage($cache . '/uploads'),
                    $csrf,
                    $views,
                    new AdminContentForm(TestUrlGenerator::contentAdmin()),
                    $settings,
                    TestUrlGenerator::contentAdmin(),
                    new \Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository($db, $settings),
                    new \Jinstan\Core\Infrastructure\View\TemplateCatalog($root . '/themes/default'),
                );
                foreach (['article' => 'admin.articles', 'page' => 'admin.pages', 'product' => 'admin.products'] as $handle => $key) {
                    $response = $controller->index(new ServerRequest('GET', '/admin/content/' . $handle), ['type' => $handle]);
                    self::assertSame(200, $response->getStatusCode());
                    $this->assertPageTitle($translator->translate($key), (string) $response->getBody());
                    $create = $controller->create(new ServerRequest('GET', '/admin/content/' . $handle . '/create'), ['type' => $handle]);
                    $this->assertPageTitle($translator->translate('content.create_title', ['name' => $translator->translate($key)]), (string) $create->getBody());
                }
                $response = $controller->index(new ServerRequest('GET', '/admin/content/custom'), ['type' => 'custom']);
                $this->assertPageTitle('文章', (string) $response->getBody());
                $navigationTranslations = new NavigationTranslationResolver($navigation, $settings);
                $menus = new NavigationAdminController(
                    $csrf,
                    $settings,
                    new NavigationListQuery($navigation, $settings, $navigationTranslations),
                    new NavigationWriter($navigation, $settings),
                    new NavigationItemFormQuery($navigation, $settings, $navigationTranslations),
                    new NavigationListPresenter($views),
                    $views,
                    TestUrlGenerator::adminDestinations(),
                    new \Jinstan\Core\Http\ResponseFactory(),
                );
                $response = $menus->index(new ServerRequest('GET', '/admin/navigation'), []);
                $this->assertPageTitle($translator->translate('admin.navigation'), (string) $response->getBody());
                self::assertStringContainsString('>导航菜单</a>', (string) $response->getBody());
            }
            $types->register(new ContentTypeDefinition('article', '自定义文章名称', [], 'content-detail', '/blog/{slug}'));
            $response = $controller->index(new ServerRequest('GET', '/admin/content/article'), ['type' => 'article']);
            $this->assertPageTitle('自定义文章名称', (string) $response->getBody());
            $malformed = (new ServerRequest('GET', '/admin/content/article'))->withQueryParams([
                'q' => ['nested'],
                'status' => ['nested'],
                'page' => ['nested'],
                'columns' => [['nested'], 'slug'],
            ]);
            self::assertSame(200, $controller->index($malformed, ['type' => 'article'])->getStatusCode());
        } finally {
            $_SESSION = $session;
            foreach (glob($cache . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($cache)) {
                rmdir($cache);
            }
        }
    }

    private function assertPageTitle(string $expected, string $html): void
    {
        self::assertMatchesRegularExpression('/<title>' . preg_quote($expected, '/') . ' · Jinstan<\/title>/', $html);
        self::assertMatchesRegularExpression('/<h1[^>]*>' . preg_quote($expected, '/') . '<\/h1>/', $html);
        self::assertMatchesRegularExpression('/<span[^>]*aria-current="page">' . preg_quote($expected, '/') . '<\/span>/', $html);
    }
}
