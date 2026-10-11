<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Application\Plugin\Services\PluginManager;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\PluginAdminPresenter;
use Jinstan\Core\Application\Plugin\Queries\PluginAdminQuery;
use Jinstan\Core\Application\Plugin\Services\PluginWriter;
use Jinstan\Core\Component\ComponentRegistry;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Field\FieldTypeRegistrar;
use Jinstan\Core\Event\EventListenerRegistrar;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\Controllers\Admin\PluginAdminController;
use Jinstan\Core\I18n\TranslationRegistrar;
use Jinstan\Core\I18n\TranslationRegistry;
use Jinstan\Core\Infrastructure\Persistence\Plugin\PluginStateRepository;
use Jinstan\Core\Infrastructure\Filesystem\Plugin\PluginDiscovery;
use Jinstan\Core\Infrastructure\Database\Plugin\PluginMigrationRunner;
use Jinstan\Core\Plugin\PluginRegistrar;
use Jinstan\Core\Plugin\PluginMigrationGateway;
use Jinstan\Core\Plugin\PluginServiceRegistrar;
use Jinstan\Core\Plugin\PluginStateReadRepository;
use Jinstan\Core\Plugin\PluginStateRepositoryContract;
use Jinstan\Core\Plugin\PluginStateWriteRepository;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Security\RateLimiter;
use Jinstan\Core\SEO\SchemaRegistrar;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\View\ViewNamespaceRegistry;
use Jinstan\Core\View\ViewRegistrar;
use Jinstan\Core\View\ViewRenderer;

final class PluginServiceProvider implements ServiceProvider
{
    private ?PluginManager $manager = null;

    public function register(Application $application): void
    {
        $container = $application->container();
        if (!$container->has(Connection::class)) {
            return;
        }

        $states = new PluginStateRepository($container->get(Connection::class));
        $services = new PluginServiceRegistrar($container);
        $registrar = new PluginRegistrar(
            $container->get(RouteRegistrar::class),
            $container->get(ComponentRegistry::class),
            $container->get(ContentReadRepository::class),
            $container->get(CsrfToken::class),
            $container->get(SettingsReadRepository::class),
            $container->get(ResponseFactory::class),
            $container->get(ContentTypeRegistry::class),
            $services,
            $container->get(FieldTypeRegistrar::class),
            $container->get(EventListenerRegistrar::class),
            $container->get(TranslationRegistrar::class),
            $container->get(ViewRegistrar::class),
            $container->get(ViewRenderer::class),
            $container->get(SchemaRegistrar::class),
            $container->get(RateLimiter::class),
        );
        $pluginMigrations = new PluginMigrationRunner(
            $container->get(Connection::class),
            $container->get(MigrationRunner::class),
        );
        $pluginDiscovery = new PluginDiscovery();
        $this->manager = new PluginManager(
            $states,
            $registrar,
            $container->get(SettingsReadRepository::class),
            $container->has(ViewNamespaceRegistry::class)
                ? $container->get(ViewNamespaceRegistry::class)
                : null,
            $pluginMigrations,
            $container->get(TranslationRegistry::class),
            $pluginDiscovery,
        );

        $container->set(PluginStateRepository::class, $states);
        $container->set(PluginStateReadRepository::class, $states);
        $container->set(PluginStateWriteRepository::class, $states);
        $container->set(PluginStateRepositoryContract::class, $states);
        $container->set(PluginRegistrar::class, $registrar);
        $container->set(PluginServiceRegistrar::class, $services);
        $container->set(PluginMigrationRunner::class, $pluginMigrations);
        $container->set(PluginMigrationGateway::class, $pluginMigrations);
        $container->set(PluginManager::class, $this->manager);
        $query = new PluginAdminQuery($this->manager);
        $presenter = new PluginAdminPresenter(
            $container->get(AdminViewRenderer::class),
            $container->get(CsrfToken::class),
        );
        $container->set(PluginAdminQuery::class, $query);
        $container->set(PluginAdminPresenter::class, $presenter);
        $writer = new PluginWriter($this->manager);
        $container->set(PluginWriter::class, $writer);
        $pluginController = new PluginAdminController(
            $query,
            $presenter,
            $writer,
            $container->get(AdminViewRenderer::class),
            $container->get(UrlGenerator::class),
            $container->get(ResponseFactory::class),
        );
        $container->set(PluginAdminController::class, $pluginController);
    }

    public function boot(Application $application): void
    {
        $this->manager?->boot($application->projectPath('plugins'));
    }
}
