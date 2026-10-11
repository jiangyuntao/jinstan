<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Application\Content\Queries\PublicContentQuery;
use Jinstan\Core\Application\Content\Queries\PublicHomepageQuery;
use Jinstan\Core\Application\Content\Queries\PublicNotFoundQuery;
use Jinstan\Core\Bootstrap\Bootstrap;
use Jinstan\Core\Component\ComponentRegistry;
use Jinstan\Core\Component\LatestContentComponent;
use Jinstan\Core\Component\NavigationMenuComponent;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Infrastructure\Persistence\SEO\RedirectRepository;
use Jinstan\Core\SEO\SchemaRegistry;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Http\Controllers\Frontend\PublicContentController;
use Jinstan\Core\Infrastructure\View\ThemeRenderer;
use Jinstan\Core\View\Presenters\Frontend\PublicContentPresenter;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HomepageTest extends TestCase
{
    public function testHomepageReturnsSuccessfulResponse(): void
    {
        $response = Bootstrap::create(dirname(__DIR__, 2))->handle(new ServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame('', (string) $response->getBody());
    }

    public function testThemeHomepageIsRenderedWithoutSelectedPage(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.name', 'Example Site');
        $settings->set('site.tagline', 'A complete homepage');
        $settings->set('site.description', 'Useful content for visitors.');
        $settings->set('site.url', 'https://example.test');
        $settings->set('site.footer', '© Example Site. All rights reserved.');
        $settings->set('site.sns.weibo', 'https://weibo.com/example');
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en');
        $settings->set('seo.default_title', 'Example Site');

        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $contents = new ContentRepository($connection);
        $service = new ContentService($types, $contents);
        $article = $service->create('article', new ContentTranslation('en', 'Published article', 'published', 'Article summary', []), ['content' => 'Body']);
        $draft = $service->create('article', new ContentTranslation('en', 'Draft article', 'draft', null, []), ['content' => 'Draft']);
        $service->publish($article->id);
        $navigation = new NavigationRepository($connection);
        $navigation->createMenu('primary', 'Primary');
        $navigation->createItem('primary', 'en', 'Articles', '/blog', '_self', 10);
        $navigation->createMenu('footer', 'Footer');
        $navigation->createItem('footer', 'en', 'Privacy', '/privacy', '_self', 10);
        $components = new ComponentRegistry();
        $components->register(new NavigationMenuComponent($navigation, $settings));
        $renderer = new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types, $components, settings: $settings);
        $controller = new PublicContentController(
            new PublicHomepageQuery($contents, $settings, $types),
            new PublicContentPresenter($renderer, new SchemaRegistry()),
            new PublicContentQuery($contents, $settings, $types, new RedirectRepository($connection)),
            new PublicNotFoundQuery($contents, $settings, $types),
            new ResponseFactory(),
        );

        $response = $controller->homepage(new ServerRequest('GET', '/'), []);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<title>Example Site</title>', $html);
        self::assertStringNotContainsString('Example Site | Example Site', $html);
        self::assertStringContainsString('A complete homepage', $html);
        self::assertStringContainsString('<h1>Example Site</h1>', $html);
        self::assertStringContainsString('<p class="hero-description">A complete homepage</p>', $html);
        self::assertStringContainsString('Published article', $html);
        self::assertStringNotContainsString('Draft article', $html);
        self::assertStringContainsString('href="/blog/published"', $html);
        self::assertStringContainsString('href="/blog"', $html);
        self::assertStringContainsString('https://example.test/', $html);
        self::assertStringContainsString('class="hero"', $html);
        self::assertStringContainsString('id="site-navigation-toggle"', $html);
        self::assertStringContainsString('href="/privacy"', $html);
        self::assertStringContainsString('Privacy', $html);
        self::assertStringNotContainsString('返回首页', $html);
        self::assertStringNotContainsString('/assets/vendor/', $html);
        self::assertStringContainsString('<footer', $html);
        self::assertStringContainsString('© Example Site. All rights reserved.', $html);
        self::assertStringContainsString('href="https://weibo.com/example"', $html);
        self::assertStringContainsString('class="ti ti-brand-weibo"', $html);
        self::assertStringContainsString('aria-label="微博"', $html);

        $page = $service->create('page', new ContentTranslation('en', 'Selected homepage', 'home', null, []), ['content' => 'Selected content']);
        $settings->set('site.homepage_id', (string) $page->id);
        self::assertStringContainsString('Published article', (string) $controller->homepage(new ServerRequest('GET', '/'), [])->getBody());

        $service->publish($page->id);
        $selected = (string) $controller->homepage(new ServerRequest('GET', '/'), [])->getBody();
        self::assertStringContainsString('Selected homepage', $selected);
        self::assertStringContainsString('Selected content', $selected);
        self::assertStringNotContainsString('Published article', $selected);
    }

    public function testSelectedNotFoundPageIsRenderedWith404Status(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.name', 'Example Site');
        $settings->set('site.url', 'https://example.test');
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en');

        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $contents = new ContentRepository($connection);
        $service = new ContentService($types, $contents);
        $page = $service->create('page', new ContentTranslation('en', 'Page not found', 'not-found', null, []), [
            'content' => 'The requested page does not exist.',
        ]);
        $service->publish($page->id);
        $settings->set('site.not_found_page_id', (string) $page->id);

        $renderer = new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types, settings: $settings);
        $controller = new PublicContentController(
            new PublicHomepageQuery($contents, $settings, $types),
            new PublicContentPresenter($renderer, new SchemaRegistry()),
            new PublicContentQuery($contents, $settings, $types, new RedirectRepository($connection)),
            new PublicNotFoundQuery($contents, $settings, $types),
            new ResponseFactory(),
        );
        $response = $controller->notFound(new ServerRequest('GET', '/missing-page'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('noindex', $response->getHeaderLine('X-Robots-Tag'));
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Page not found', (string) $response->getBody());
        self::assertStringContainsString('The requested page does not exist.', (string) $response->getBody());
    }

    public function testSelectedHtmlHomepageCanRenderCmsTemplateTags(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.name', 'Tagged Site');
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en');

        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $contents = new ContentRepository($connection);
        $service = new ContentService($types, $contents);
        $article = $service->create('article', new ContentTranslation('en', 'CMS tag article', 'tag-article', 'Article summary', []));
        $product = $service->create('product', new ContentTranslation('en', 'CMS tag product', 'tag-product', null, []), [
            'price' => 99,
            'sku' => [['title' => '标准版', 'code' => 'TAG-001', 'stock' => 10, 'price' => 99]],
        ]);
        $service->publish($article->id);
        $service->publish($product->id);

        $navigation = new NavigationRepository($connection);
        $navigation->createMenu('primary', 'Primary');
        $navigation->createItem('primary', 'en', 'Documentation', '/docs', '_self', 10);
        $components = new ComponentRegistry();
        $components->register(new NavigationMenuComponent($navigation));
        $components->register(new LatestContentComponent($contents, $types, $settings));

        $source = <<<'HTML'
<!doctype html>
<html lang="{$locale}">
<head><title>{$seo_title}</title>{$seo_tags}</head>
<body>
{=component('navigation-menu', ['handle' => 'primary'], $content, $translation)}
{=component('latest-content', ['type' => 'article', 'limit' => 3, 'heading' => 'Latest articles'], $content, $translation)}
{=component('latest-content', ['type' => 'product', 'limit' => 3, 'heading' => 'Products'], $content, $translation)}
</body>
</html>
HTML;
        $page = $service->create('page', new ContentTranslation('en', 'Custom homepage', 'home', null, [
            'content' => ['format' => 'html_document', 'value' => $source],
        ]));
        $service->publish($page->id);
        $settings->set('site.homepage_id', (string) $page->id);

        $renderer = new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types, $components, settings: $settings);
        $controller = new PublicContentController(
            new PublicHomepageQuery($contents, $settings, $types),
            new PublicContentPresenter($renderer, new SchemaRegistry()),
            new PublicContentQuery($contents, $settings, $types, new RedirectRepository($connection)),
            new PublicNotFoundQuery($contents, $settings, $types),
            new ResponseFactory(),
        );
        $html = (string) $controller->homepage(new ServerRequest('GET', '/'), [])->getBody();

        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('Documentation', $html);
        self::assertStringContainsString('Latest articles', $html);
        self::assertStringContainsString('CMS tag article', $html);
        self::assertStringContainsString('href="/blog/tag-article"', $html);
        self::assertStringContainsString('CMS tag product', $html);
        self::assertStringContainsString('href="/product/tag-product"', $html);
    }

    public function testKoreanThemeUsesKoreanInterfaceCopy(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.name', '예시 사이트');
        $settings->set('site.default_locale', 'zh-CN');
        $settings->set('site.enabled_locales', 'zh-CN');

        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $renderer = new ThemeRenderer(dirname(__DIR__, 2) . '/themes/korean', $types, settings: $settings);

        $html = $renderer->homepage([], 'zh-CN', 'https://example.test/');

        self::assertStringContainsString('본문으로 건너뛰기', $html);
        self::assertStringContainsString('사이트 메뉴', $html);
        self::assertStringNotContainsString('跳到正文', $html);
    }

    public function testArabicLocaleUsesRightToLeftDirection(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.locales', json_encode(['ar' => ['name' => 'العربية', 'direction' => 'rtl'], 'zh-CN' => ['name' => '简体中文', 'direction' => 'ltr']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $renderer = new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types, settings: $settings);

        self::assertStringContainsString('<html lang="ar" dir="rtl">', $renderer->homepage([], 'ar', '/ar'));
    }
}
