<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Component\BreadcrumbComponent;
use Jinstan\Core\Component\ComponentRegistry;
use Jinstan\Core\Component\RelatedPostsComponent;
use Jinstan\Core\Component\NavigationMenuComponent;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Application\Plugin\Services\PluginManager;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Plugin\PluginRegistrar;
use Jinstan\Core\Plugin\PluginServiceRegistrar;
use Jinstan\Core\Infrastructure\Persistence\Plugin\PluginStateRepository;
use Jinstan\Core\Infrastructure\Filesystem\Plugin\PluginDiscovery;
use Jinstan\Core\Infrastructure\Filesystem\Routing\LocalRouteFileLoader;
use Jinstan\Core\Infrastructure\Filesystem\Security\FileRateLimiter;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\View\ThemeRenderer;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Support\Container;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ContentServiceTest extends TestCase
{
    public function testDefaultContentTypesUseSharedContentStorage(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-content-' . bin2hex(random_bytes(6));
        mkdir($projectPath, 0750, true);
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $projectPath . '/content.sqlite']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $pluginContainer = new Container();
        $router = new Router($pluginContainer);
        $pluginContents = new ContentRepository($connection);
        $csrf = new CsrfToken();
        $rateLimiter = new FileRateLimiter($projectPath . '/rate-limits');
        $pluginComponents = new ComponentRegistry();
        $pluginComponents->register(new NavigationMenuComponent(new NavigationRepository($connection)));
        $settings = new SettingsRepository($connection);
        $plugins = new PluginManager(
            new PluginStateRepository($connection),
            new PluginRegistrar(
                new RouteRegistrar($router, new LocalRouteFileLoader()),
                $pluginComponents,
                $pluginContents,
                $csrf,
                $settings,
                new ResponseFactory(),
                services: new PluginServiceRegistrar($pluginContainer),
                rateLimiter: $rateLimiter,
            ),
            $settings,
            discovery: new PluginDiscovery(),
        );
        $plugins->boot(dirname(__DIR__, 2) . '/plugins');
        self::assertSame('Hello from plugin.', (string) $router->dispatch(new ServerRequest('GET', '/hello-plugin'))?->getBody());
        self::assertSame('1.0.0', $connection->fetchOne("SELECT version FROM plugins WHERE name = 'hello-plugin'"));
        $externalOrder = array_values(array_filter(
            $plugins->plugins(),
            static fn (array $plugin): bool => $plugin['name'] === 'external-order',
        ))[0];
        self::assertSame('Jinstan', $externalOrder['author']);
        self::assertCount(2, $externalOrder['settings']);
        $plugins->saveSettings('external-order', [
            'submit_url' => 'https://orders.example.test/submit',
            'parameters' => '{"product":"product_id","customer_email":"email"}',
        ]);
        self::assertSame('https://orders.example.test/submit', $settings->get('plugin.external-order.submit_url'));
        self::assertSame('{"product":"product_id","customer_email":"email"}', $settings->get('plugin.external-order.parameters'));
        try {
            $plugins->saveSettings('external-order', ['submit_url' => 'ftp://example.test/order', 'parameters' => '{}']);
            self::fail('非 HTTP URL 应被拒绝。');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('HTTP 或 HTTPS URL', $exception->getMessage());
        }
        self::assertSame('https://orders.example.test/submit', $settings->get('plugin.external-order.submit_url'));
        $plugins->saveSettings('external-order', [
            'submit_url' => '',
            'parameters' => '{"product_id":"product_id","email":"email"}',
        ]);
        $plugins->disable('hello-plugin');
        self::assertSame(0, (int) $connection->fetchOne("SELECT enabled FROM plugins WHERE name = 'hello-plugin'"));
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $repository = new ContentRepository($connection);
        $service = new ContentService($types, $repository);

        $product = $service->create('product', new ContentTranslation('en', 'Starter', 'starter', null, []), [
            'description' => 'A starter product',
            'sku' => [['title' => 'Starter', 'code' => 'START-1', 'stock' => 5, 'price' => '19.95']],
            'cover_image' => 'ab/cd/abcdefabcdefabcdefabcdefabcdefab.png',
            'gallery' => ['12/34/1234567890abcdef1234567890abcdef.webp'],
            'content' => 'Product details',
        ]);
        $parentPage = $service->create('page', new ContentTranslation('en', 'About', 'about', null, []), ['content' => 'About us']);
        $childPage = $service->create('page', new ContentTranslation('en', 'Team', 'team', null, []), ['content' => 'Our team'], null, $parentPage->id);
        $article = $service->create('article', new ContentTranslation('en', 'Guide', 'guide', null, []), ['content' => 'Read this first.']);
        $newerArticle = $service->create('article', new ContentTranslation('en', 'Latest guide', 'latest-guide', null, []), ['content' => 'Read this next.']);
        $articles = $repository->list('article');
        $taxonomy = new TaxonomyRepository($connection);
        $term = $taxonomy->createTerm('category', 'en', 'Guides', 'guides');
        $taxonomy->assign($article->id, $term);
        $repository->relate($article->id, 'mentions', $product->id);
        $repository->relate($article->id, 'related', $product->id);
        $translation = $repository->translation($product->id, 'en');
        $service->update($product->id, new ContentTranslation('zh-CN', '入门版', 'starter-zh', null, ['description' => '入门产品']), [
            'sku' => [['title' => 'Starter', 'code' => 'START-2', 'stock' => 3, 'price' => 29]],
            'cover_image' => 'ab/cd/abcdefabcdefabcdefabcdefabcdefab.png',
        ]);
        $service->publish($product->id);
        $updated = $repository->find($product->id);
        $related = $repository->related($article->id, 'mentions');

        self::assertSame(5, (int) $connection->fetchOne('SELECT COUNT(*) FROM contents'));
        self::assertSame($parentPage->id, $repository->find($childPage->id)?->parentId);
        self::assertSame([$newerArticle->id, $article->id], array_map(static fn ($content): int => $content->id, $articles));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM content_terms'));
        self::assertSame(19.95, $translation?->data['sku'][0]['price']);
        self::assertSame('A starter product', $translation?->data['description']);
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM content_relations'));
        self::assertSame($product->id, $related[0]->id);
        self::assertSame('入门产品', $repository->translation($product->id, 'zh-CN')?->data['description']);
        self::assertSame('published', $updated?->status);
        $service->setPublished($product->id, false);
        self::assertSame('draft', $repository->find($product->id)?->status);
        $service->setPublished($product->id, true);
        self::assertSame('published', $repository->find($product->id)?->status);
        $order = $router->dispatch((new ServerRequest('POST', '/external-order'))->withParsedBody(['_token' => $csrf->value(), 'product_id' => $product->id, 'email' => 'buyer@example.test']));
        self::assertSame(200, $order?->getStatusCode());
        self::assertStringContainsString('FAKE-' . $product->id, (string) $order?->getBody());
        $invalidOrder = $router->dispatch((new ServerRequest('POST', '/external-order'))->withParsedBody([
            '_token' => $csrf->value(),
            'product_id' => $product->id,
            'email' => 'invalid-email',
        ]));
        self::assertSame(422, $invalidOrder?->getStatusCode());
        self::assertSame('请输入有效邮箱。', (string) $invalidOrder?->getBody());
        $invalidToken = $router->dispatch((new ServerRequest('POST', '/external-order'))->withParsedBody([
            '_token' => 'invalid',
            'product_id' => $product->id,
            'email' => 'buyer@example.test',
        ]));
        self::assertSame(419, $invalidToken?->getStatusCode());
        for ($attempt = 3; $attempt <= 10; ++$attempt) {
            $limitedOrder = $router->dispatch((new ServerRequest('POST', '/external-order'))->withParsedBody([
                '_token' => $csrf->value(),
                'product_id' => $product->id,
                'email' => 'invalid-email',
            ]));
            self::assertSame(422, $limitedOrder?->getStatusCode());
        }
        $limitedOrder = $router->dispatch((new ServerRequest('POST', '/external-order'))->withParsedBody([
            '_token' => $csrf->value(),
            'product_id' => $product->id,
            'email' => 'buyer@example.test',
        ]));
        self::assertSame(429, $limitedOrder?->getStatusCode());
        self::assertGreaterThan(0, (int) $limitedOrder?->getHeaderLine('Retry-After'));
        $plugins->uninstall('external-order');
        self::assertFalse($plugins->plugin('external-order')['installed']);
        self::assertNull($settings->get('plugin.external-order.parameters'));
        $reloadedRouter = new Router();
        $reloadedPlugins = new PluginManager(
            new PluginStateRepository($connection),
            new PluginRegistrar(
                new RouteRegistrar($reloadedRouter, new LocalRouteFileLoader()),
                new ComponentRegistry(),
                $repository,
                $csrf,
                $settings,
                new ResponseFactory(),
                rateLimiter: $rateLimiter,
            ),
            $settings,
            discovery: new PluginDiscovery(),
        );
        $reloadedPlugins->boot(dirname(__DIR__, 2) . '/plugins');
        self::assertFalse($reloadedPlugins->plugin('external-order')['installed']);
        $reloadedPlugins->enable('external-order');
        self::assertTrue($reloadedPlugins->plugin('external-order')['installed']);
        $productHtml = (new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types, $pluginComponents, dirname(__DIR__, 2) . '/plugins'))->content($product, $translation, '/product/starter');
        self::assertStringContainsString('<h1>Starter</h1>', $productHtml);
        self::assertStringContainsString('class="product-showcase"', $productHtml);
        self::assertStringContainsString('class="product-media"', $productHtml);
        self::assertStringContainsString('data-product-main-image', $productHtml);
        self::assertStringContainsString('data-product-thumbnail', $productHtml);
        self::assertStringContainsString('aria-pressed="false"', $productHtml);
        self::assertStringContainsString('class="product-summary"', $productHtml);
        self::assertStringContainsString('class="product-introduction"', $productHtml);
        self::assertStringContainsString('data-field="description"', $productHtml);
        self::assertStringContainsString('A starter product', $productHtml);
        self::assertStringContainsString('data-field="sku"', $productHtml);
        self::assertStringContainsString('START-1', $productHtml);
        self::assertStringContainsString('data-field="cover_image"', $productHtml);
        self::assertStringContainsString('src="/media/ab/cd/abcdefabcdefabcdefabcdefabcdefab.png"', $productHtml);
        self::assertStringContainsString('data-field="gallery"', $productHtml);
        self::assertStringContainsString('canonical" href="/product/starter', $productHtml);
        $productListHtml = (new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types))->listing(
            $types->get('product'),
            ['items' => [[
                'title' => 'Starter',
                'url' => '/product/starter',
                'excerpt' => null,
                'data' => $product->data,
            ]], 'total' => 1, 'page' => 1, 'pages' => 1, 'perPage' => 12],
            'en',
            '/products',
            null,
            null,
        );
        self::assertStringContainsString('src="/media/ab/cd/abcdefabcdefabcdefabcdefabcdefab.png"', $productListHtml);
        $components = new ComponentRegistry();
        $components->register(new BreadcrumbComponent($types));
        $components->register(new RelatedPostsComponent($repository));
        $components->register(new NavigationMenuComponent(new NavigationRepository($connection)));
        $articleHtml = (new ThemeRenderer(dirname(__DIR__, 2) . '/themes/default', $types, $components))->content($article, $repository->translation($article->id, 'en'));
        self::assertStringContainsString('data-field="content"', $articleHtml);
        self::assertStringContainsString('Read this first.', $articleHtml);
        self::assertStringContainsString('<a href="/blog">文章</a>', $articleHtml);
        self::assertStringNotContainsString('class="detail article-detail"', $articleHtml);
        self::assertStringContainsString('相关文章', $articleHtml);
        self::assertStringContainsString('theme-breadcrumb', $articleHtml);

        $repository->delete($product->id);
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM contents'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM content_relations'));

        $image = $projectPath . '/pixel.png';
        file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Jv6sAAAAASUVORK5CYII='));
        $attachments = new ContentAttachmentStorage($projectPath . '/media');
        $path = $attachments->store($image, 'pixel.png', true);
        self::assertMatchesRegularExpression('#^[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{32}\\.png$#', $path);
        self::assertFileExists($projectPath . '/media/' . $path);
        self::assertSame('image/png', $attachments->read($path)['mime'] ?? null);
        self::assertNull($attachments->read('../database.sqlite'));
        $attachments->delete($path);
        self::assertFileDoesNotExist($projectPath . '/media/' . $path);
    }
}
