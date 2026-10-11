<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Application\Content\Queries\PublicContentQuery;
use Jinstan\Core\Application\Content\Queries\PublicHomepageQuery;
use Jinstan\Core\Application\Content\Queries\PublicNotFoundQuery;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentUrls;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Infrastructure\Persistence\SEO\RedirectRepository;
use Jinstan\Core\SEO\SchemaRegistry;
use Jinstan\Core\Http\Controllers\Frontend\SitemapController;
use Jinstan\Core\Application\SEO\Queries\SitemapQuery;
use Jinstan\Core\View\Presenters\Frontend\SitemapPresenter;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Core\Http\Routing\ContentRoutes;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Http\Controllers\Frontend\PublicContentController;
use Jinstan\Core\Infrastructure\View\TemplateCatalog;
use Jinstan\Core\Infrastructure\View\ThemeRenderer;
use Jinstan\Core\View\Presenters\Frontend\PublicContentPresenter;
use Jinstan\Tests\Support\TestUrlGenerator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ContentPresentationTest extends TestCase
{
    public function testCustomRoutesPaginationAndTemplateInheritance(): void
    {
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($db);
        $settings->set('site.url', 'https://example.test');
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en,zh-CN');
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $types->register(new ContentTypeDefinition('faq', 'FAQ', [], 'content-detail', '/{slug}.html'));
        $type = new ContentTypeDefinition('review', 'Reviews', [], 'content-detail', '/reviews/{slug}', listEnabled: true, listPath: '/reviews', listTemplate: 'content-list', perPage: 2);
        $types->register($type);
        $repository = new ContentRepository($db);
        $catalog = new TemplateCatalog(dirname(__DIR__, 2) . '/themes/default');
        $service = new ContentService($types, $repository, new TaxonomyRepository($db), $catalog);
        $faq = $service->create('faq', new ContentTranslation('en', 'FAQ entry', 'faq-entry', null, []));
        $service->publish($faq->id);
        $chineseOnly = $service->create('faq', new ContentTranslation('zh-CN', '中文回退内容', 'chinese-only', null, []));
        $service->publish($chineseOnly->id);
        $content = null;
        for ($i = 1; $i <= 5; ++$i) {
            $content = $service->saveWithTerms('review', null, new ContentTranslation('en', 'Review ' . $i, 'review-' . $i, 'Summary', []), [], [], null, null, $i < 5);
        }
        self::assertNull($content->template);
        $service->update($content->id, new ContentTranslation('zh-CN', '评测', 'pingce', null, []), []);
        $renderer = new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types, settings: $settings);
        $controller = new PublicContentController(
            new PublicHomepageQuery($repository, $settings, $types),
            new PublicContentPresenter($renderer, new SchemaRegistry()),
            new PublicContentQuery($repository, $settings, $types, new RedirectRepository($db)),
            new PublicNotFoundQuery($repository, $settings, $types),
            new ResponseFactory(),
        );
        $router = new Router();
        ContentRoutes::register(new RouteRegistrar($router), $types, $controller);
        self::assertSame(200, $router->dispatch(new ServerRequest('GET', '/faq-entry.html'))->getStatusCode());
        self::assertStringContainsString('中文回退内容', (string) $router->dispatch(new ServerRequest('GET', '/chinese-only.html'))->getBody());
        self::assertStringContainsString('中文回退内容', (string) $router->dispatch(new ServerRequest('GET', '/en/chinese-only.html'))->getBody());
        $response = $router->dispatch(new ServerRequest('GET', '/reviews'));
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Review 4', $html);
        self::assertStringNotContainsString('内容列表', $html);
        self::assertStringNotContainsString('class="row-number"', $html);
        self::assertStringContainsString('<span>共 4 篇 · 第 1 / 2 页</span>', $html);
        self::assertStringNotContainsString('Review 5', $html);
        self::assertStringNotContainsString('Review 2', $html);
        self::assertStringContainsString('href="/reviews/review-4"', $html);
        self::assertStringContainsString('href="/reviews?page=2"', $html);
        $oldestFirst = $repository->publishedPage('review', 'en', 1, 10, 'oldest');
        self::assertSame('Review 1', $oldestFirst['items'][0]['title']);
        $titleDescending = $repository->publishedPage('review', 'en', 1, 10, 'title_desc');
        self::assertSame('Review 4', $titleDescending['items'][0]['title']);
        $second = $router->dispatch((new ServerRequest('GET', '/reviews?page=2'))->withQueryParams(['page' => '2']));
        self::assertStringContainsString('https://example.test/reviews?page=2', (string) $second->getBody());
        self::assertStringContainsString('Review 1', (string) $second->getBody());
        self::assertSame(404, $router->dispatch((new ServerRequest('GET', '/reviews'))->withQueryParams(['page' => '3']))->getStatusCode());
        self::assertSame(404, $router->dispatch((new ServerRequest('GET', '/reviews'))->withQueryParams(['page' => ['bad']]))->getStatusCode());
        self::assertSame(200, $router->dispatch(new ServerRequest('GET', '/reviews/review-1'))->getStatusCode());
        self::assertSame(404, $router->dispatch(new ServerRequest('GET', '/reviews/review-5'))->getStatusCode());
        self::assertSame(404, $router->dispatch(new ServerRequest('GET', '/fr/reviews'))->getStatusCode());
        $products = $router->dispatch(new ServerRequest('GET', '/products'));
        self::assertSame(200, $products->getStatusCode());
        self::assertStringContainsString('<header class="listing-head"><h1>产品</h1></header>', (string) $products->getBody());
        self::assertStringNotContainsString('产品中心', (string) $products->getBody());
        self::assertSame(200, $router->dispatch(new ServerRequest('GET', '/blog'))->getStatusCode());
        self::assertSame(404, $router->dispatch(new ServerRequest('GET', '/pages'))->getStatusCode());
        $service->publish($content->id);
        $zh = $router->dispatch(new ServerRequest('GET', '/zh-CN/reviews'));
        self::assertStringContainsString('href="/zh-CN/reviews/pingce"', (string) $zh->getBody());
        self::assertStringNotContainsString('Review 4', (string) $zh->getBody());
        $detail = $router->dispatch(new ServerRequest('GET', '/zh-CN/reviews/pingce'));
        self::assertStringContainsString('lang="zh-CN"', (string) $detail->getBody());
        self::assertStringContainsString('https://example.test/zh-CN/reviews/pingce', (string) $detail->getBody());
        $translation = $repository->translation($content->id, 'en');
        $service->saveWithTerms('review', $content->id, $translation, [], [], null, null, true, 'article-detail');
        self::assertSame('article-detail', $repository->find($content->id)->template);
        $service->saveWithTerms('review', $content->id, $translation, [], [], null, null, true);
        self::assertSame('article-detail', $repository->find($content->id)->template);
        $service->saveWithTerms('review', $content->id, $translation, [], [], null, null, true, '');
        self::assertNull($repository->find($content->id)->template);
        $types->register(new ContentTypeDefinition('review', 'Reviews', [], 'article-detail', '/reviews/{slug}', listEnabled: true, listPath: '/reviews'));
        self::assertStringContainsString('<article class="detail">', (string) $router->dispatch(new ServerRequest('GET', '/reviews/review-5'))->getBody());
        $sitemap = (new SitemapController(
            new SitemapQuery($repository, $types, $settings),
            new SitemapPresenter(),
            new ResponseFactory(),
        ))->show(new ServerRequest('GET', '/sitemap.xml'), []);
        self::assertStringContainsString('https://example.test/reviews/review-1', (string) $sitemap->getBody());
        self::assertStringContainsString('<loc>https://example.test/reviews</loc>', (string) $sitemap->getBody());
        $types->register(new ContentTypeDefinition('review', 'Reviews', [], 'missing-template', '/reviews/{slug}'));
        $fallback = $renderer->content($repository->find($content->id), $translation);
        self::assertStringContainsString('Review 5', $fallback);
        self::assertStringNotContainsString('ARTICLE', $fallback);
        self::assertSame(404, $controller->listing(new ServerRequest('GET', '/reviews'), ['type' => 'review'])->getStatusCode());
        $this->expectException(\InvalidArgumentException::class);
        $service->saveWithTerms('review', $content->id, $translation, [], [], null, null, true, 'product-list');
    }

    public function testAdminRejectsWrongTemplateKindsAndRouteConflicts(): void
    {
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $db->insert('users', ['email' => 'presentation@example.test', 'password_hash' => 'unused', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $id = (int) $db->lastInsertId();
        $db->insert('user_roles', ['user_id' => $id, 'role_key' => 'administrator']);
        $_SESSION = ['_jinstan_user_id' => $id];
        try {
            $types = new ContentTypeRegistry();
            DefaultContentTypes::register($types);
            $settings = new SettingsRepository($db);
            $fieldTypes = new \Jinstan\Core\Field\FieldTypeRegistry();
            \Jinstan\Core\Field\DefaultFieldTypes::register($fieldTypes);
            $editable = new EditableContentTypes($settings, $fieldTypes);
            $csrf = new \Jinstan\Core\Security\CsrfToken();
            $router = new Router();
            $router->get('/plugin-page', static fn () => new \Nyholm\Psr7\Response());
            $controller = new \Jinstan\Core\Http\Controllers\Admin\ContentTypeAdminController(
                $types,
                $editable,
                $csrf,
                new AdminViewRenderer(dirname(__DIR__, 2) . '/core/resources/views/admin', sys_get_temp_dir() . '/jinstan-presentation-admin', TestUrlGenerator::adminViews()),
                TestUrlGenerator::adminDestinations(),
                new \Jinstan\Core\Http\ResponseFactory(),
                new TaxonomyRepository($db, $settings),
                new TemplateCatalog(dirname(__DIR__, 2) . '/themes/default'),
                $router,
                new \Jinstan\Core\Application\Content\Services\ContentTypeDeletionService(
                    $types,
                    $editable,
                    new ContentRepository($db),
                    new \Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage(sys_get_temp_dir() . '/jinstan-presentation-attachments'),
                ),
            );
            $input = ['_token' => $csrf->value(), 'handle' => 'review', 'label' => 'Review', 'fields' => [], 'defaultTemplate' => 'content-detail', 'urlPattern' => '/reviews/{slug}', 'listEnabled' => '1', 'listPath' => '/reviews', 'listTemplate' => 'content-list'];
            foreach ([['defaultTemplate' => 'article-list'], ['listTemplate' => 'page-detail'], ['listPath' => '/plugin-page'], ['urlPattern' => '/blog/{slug}']] as $bad) {
                $response = $controller->save((new ServerRequest('POST', '/admin/content-types'))->withParsedBody(array_replace($input, $bad)), []);
                self::assertSame(422, $response->getStatusCode());
                self::assertNull($settings->get('content.types'));
            }
            self::assertSame(303, $controller->save((new ServerRequest('POST', '/admin/content-types'))->withParsedBody($input), [])->getStatusCode());
        } finally {
            $_SESSION = [];
        }
    }

    public function testCatalogConfigurationAndAdminRendering(): void
    {
        $catalog = new TemplateCatalog(dirname(__DIR__, 2) . '/themes/default');
        self::assertCount(3, $catalog->options('list'));
        self::assertCount(4, $catalog->options('detail'));
        self::assertArrayNotHasKey('article', $catalog->options('detail'));
        self::assertNull($catalog->path('fallback'));
        $undeclared = sys_get_temp_dir() . '/jinstan-undeclared-theme-' . bin2hex(random_bytes(5));
        mkdir($undeclared . '/templates', 0750, true);
        file_put_contents($undeclared . '/templates/example.jst', '<h1>Undeclared</h1>');
        file_put_contents($undeclared . '/templates/example-list.jst', '<h1>Undeclared list</h1>');
        $discovered = new TemplateCatalog($undeclared);
        self::assertSame(['example' => 'example'], $discovered->options('detail'));
        self::assertSame(['example-list' => 'example-list'], $discovered->options('list'));
        self::assertSame(['example' => 'templates/example.jst'], $discovered->files('detail'));
        self::assertNull($discovered->path('late'));
        file_put_contents($undeclared . '/templates/late.jst', '<h1>Late</h1>');
        file_put_contents($undeclared . '/theme.json', json_encode([
            'templates' => ['example' => ['kind' => 'detail', 'label' => 'Changed']],
        ], JSON_THROW_ON_ERROR));
        self::assertNull($discovered->path('late'));
        self::assertSame(['example' => 'example'], $discovered->options('detail'));
        unlink($undeclared . '/templates/late.jst');
        unlink($undeclared . '/theme.json');

        self::assertArrayHasKey('product-detail', $catalog->options('detail'));
        self::assertNull($catalog->path('../layouts/default'));
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $fieldTypes = new \Jinstan\Core\Field\FieldTypeRegistry();
        \Jinstan\Core\Field\DefaultFieldTypes::register($fieldTypes);
        $editable = new EditableContentTypes(new SettingsRepository($db), $fieldTypes);
        $definition = ['handle' => 'custom', 'label' => '自定义', 'fields' => [], 'defaultTemplate' => 'content-detail', 'urlPattern' => '/custom/{slug}', 'listEnabled' => '1', 'listPath' => '/custom', 'listTemplate' => 'content-list', 'perPage' => '8'];
        $type = $editable->definition($definition);
        $editable->save([$editable->export($type)]);
        $types = new ContentTypeRegistry();
        $editable->load($types);
        self::assertSame(8, $types->get('custom')->perPage);
        self::assertTrue($types->get('custom')->listEnabled);
        foreach ([['urlPattern' => '/admin/{slug}'], ['listPath' => '//evil.test'], ['perPage' => 0], ['defaultTemplate' => '../outside']] as $bad) {
            try {
                $editable->definition(array_replace($definition, $bad));
                self::fail('Invalid configuration accepted');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        $html = (new AdminViewRenderer(dirname(__DIR__, 2) . '/core/resources/views/admin', sys_get_temp_dir() . '/jinstan-presentation-admin', TestUrlGenerator::adminViews()))->render('content-types', '编辑', [
            'definition' => $definition, 'fieldsJson' => '[]', 'error' => null, 'taxonomyGroups' => [],
            'detailTemplates' => $catalog->files('detail'), 'listTemplates' => $catalog->files('list'),
            'hasProtectedFields' => false,
        ]);
        self::assertStringContainsString('前台展示', $html);
        self::assertStringContainsString('x-data="{ enabled: true }"', $html);
        self::assertStringContainsString('id="list-template"', $html);
        self::assertStringContainsString('templates/content-detail.jst', $html);
        self::assertStringContainsString('templates/content-list.jst', $html);
        $types->register(new ContentTypeDefinition('other', 'Other', [], 'content-detail', '/custom/{slug}'));
        $this->expectException(\InvalidArgumentException::class);
        ContentUrls::validateTypes($types);
    }
}
