<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Plugin\Services\PluginManager;
use Jinstan\Core\Http\Controllers\Admin\PluginAdminController;
use Jinstan\Core\View\Presenters\Admin\PluginAdminPresenter;
use Jinstan\Core\Application\Plugin\Queries\PluginAdminQuery;
use Jinstan\Core\Application\Plugin\Services\PluginWriter;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Field\FieldTypeRegistrar;
use Jinstan\Core\Event\EventListenerRegistrar;
use Jinstan\Core\I18n\TranslationRegistrar;
use Jinstan\Core\I18n\Translator;
use Jinstan\Core\Infrastructure\Persistence\Plugin\PluginStateRepository;
use Jinstan\Core\Infrastructure\Database\Plugin\PluginMigrationRunner;
use Jinstan\Core\Plugin\PluginRegistrar;
use Jinstan\Core\Plugin\PluginServiceRegistrar;
use Jinstan\Core\Plugin\PluginStateReadRepository;
use Jinstan\Core\Plugin\PluginStateRepositoryContract;
use Jinstan\Core\Plugin\PluginStateWriteRepository;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Providers\AdminServiceProvider;
use Jinstan\Core\Providers\ContentServiceProvider;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Providers\PluginServiceProvider;
use Jinstan\Core\Providers\ThemeServiceProvider;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Security\CsrfTokenProvider;
use Jinstan\Core\View\ViewNamespaceRegistry;
use Jinstan\Core\View\ViewRegistrar;
use Jinstan\Core\View\ViewRenderer;
use Jinstan\Tests\Support\InstalledTestProject;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Jinstan\Plugins\HelloPlugin\GreetingService;

final class PluginServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testItDoesNotRegisterPluginServicesBeforeInstallation(): void
    {
        $application = $this->application(InstalledTestProject::create(false));

        self::assertFalse($application->container()->has(PluginManager::class));
        self::assertFalse($application->container()->has(PluginAdminController::class));
    }

    public function testItRegistersAndBootsOfficialPlugins(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $container = $application->container();

        self::assertInstanceOf(PluginStateRepository::class, $container->get(PluginStateRepository::class));
        self::assertSame($container->get(PluginStateRepository::class), $container->get(PluginStateReadRepository::class));
        self::assertSame($container->get(PluginStateRepository::class), $container->get(PluginStateWriteRepository::class));
        self::assertSame($container->get(PluginStateRepository::class), $container->get(PluginStateRepositoryContract::class));
        self::assertInstanceOf(PluginManager::class, $container->get(PluginManager::class));
        self::assertFalse($container->has(\Jinstan\Core\Plugin\PluginDiscoveryGateway::class));
        self::assertFalse($container->has(\Jinstan\Core\Infrastructure\Filesystem\Plugin\PluginDiscovery::class));
        self::assertInstanceOf(PluginRegistrar::class, $container->get(PluginRegistrar::class));
        self::assertInstanceOf(PluginServiceRegistrar::class, $container->get(PluginServiceRegistrar::class));
        self::assertSame($container->get(FieldTypeRegistrar::class), $container->get(PluginRegistrar::class)->fieldTypes());
        self::assertSame($container->get(EventListenerRegistrar::class), $container->get(PluginRegistrar::class)->events());
        self::assertSame($container->get(TranslationRegistrar::class), $container->get(PluginRegistrar::class)->translations());
        self::assertSame('来自插件的问候。', $container->get(Translator::class)->translate('hello-plugin::greeting'));
        self::assertSame($container->get(ViewRegistrar::class), $container->get(PluginRegistrar::class)->views());
        self::assertSame($container->get(ViewRenderer::class), $container->get(PluginRegistrar::class)->viewRenderer());
        self::assertStringContainsString(
            '<h1>来自插件的问候。</h1>',
            $container->get(\Jinstan\Core\Infrastructure\View\AdminViewRenderer::class)->render(
                'hello-plugin::admin-example.jst',
                '插件示例',
            ),
        );
        self::assertInstanceOf(GreetingService::class, $container->get(GreetingService::class));
        self::assertSame($container->get(CsrfToken::class), $container->get(CsrfTokenProvider::class));
        $container->get(PluginRegistrar::class)->contentTypes()->register(
            new ContentTypeDefinition('directory', '目录', [], 'content-detail', '/directory/{slug}'),
        );
        self::assertSame('目录', $container->get(ContentTypeRegistry::class)->get('directory')->label);
        self::assertInstanceOf(PluginAdminController::class, $container->get(PluginAdminController::class));
        self::assertInstanceOf(PluginAdminQuery::class, $container->get(PluginAdminQuery::class));
        self::assertInstanceOf(PluginAdminPresenter::class, $container->get(PluginAdminPresenter::class));
        self::assertInstanceOf(PluginWriter::class, $container->get(PluginWriter::class));
        self::assertSame(
            realpath($application->projectPath('plugins/ExternalOrderPlugin/views/components/external-order-form.jst')),
            $container->get(ViewNamespaceRegistry::class)->resolve(
                'external-order::components/external-order-form.jst',
            ),
        );

        $plugins = $container->get(PluginManager::class)->plugins();
        self::assertSame(['external-order', 'hello-plugin'], array_column($plugins, 'name'));
        self::assertSame([true, true], array_column($plugins, 'enabled'));
        $settingsPlugin = $container->get(PluginAdminQuery::class)->settings('external-order');
        self::assertSame('external-order', $settingsPlugin['name']);
        self::assertCount(2, $settingsPlugin['settings']);
        $settingsView = $container->get(PluginAdminPresenter::class)->settings($settingsPlugin);
        self::assertSame('plugin-settings', $settingsView->template);
        self::assertSame('插件设置', $settingsView->title);

        try {
            $container->get(PluginAdminQuery::class)->settings('hello-plugin');
            self::fail('Expected a plugin without settings to be rejected.');
        } catch (\OutOfBoundsException $exception) {
            self::assertSame('Plugin settings not found.', $exception->getMessage());
        }

        $writer = $container->get(PluginWriter::class);
        $writer->toggle('hello-plugin', ['enabled' => '0']);
        self::assertFalse($container->get(PluginManager::class)->plugin('hello-plugin')['enabled']);
        $writer->saveSettings('external-order', [
            'settings' => [
                'submit_url' => '',
                'parameters' => '{"order_id":"product_id"}',
            ],
        ]);
        self::assertSame(
            '{"order_id":"product_id"}',
            $container->get(PluginAdminQuery::class)->settings('external-order')['settings'][1]['value'],
        );

        try {
            $writer->saveSettings('external-order', ['settings' => ['parameters' => ['invalid']]]);
            self::fail('Expected nested plugin setting input to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('接收参数格式无效。', $exception->getMessage());
        }

        $response = $container->get(Router::class)->dispatch(new ServerRequest('GET', '/hello-plugin'));
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello from plugin.', (string) $response->getBody());
    }

    private function application(InstalledTestProject $project): Application
    {
        $application = new Application($project->path, 'testing');
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->register(new ContentServiceProvider());
        $application->register(new ThemeServiceProvider());
        $application->register(new AdminServiceProvider());
        $application->register(new PluginServiceProvider());
        $application->boot();

        return $application;
    }
}
