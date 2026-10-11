<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Application\Update\MaintenanceModeGateway;
use Jinstan\Core\Config\Config;
use Jinstan\Core\Infrastructure\Config\PhpConfigLoader;
use Jinstan\Core\Infrastructure\Logging\LoggerFactory;
use Jinstan\Core\Infrastructure\Filesystem\I18n\PhpTranslationCatalogLoader;
use Jinstan\Core\Infrastructure\Filesystem\Routing\LocalRouteFileLoader;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Event\EventDispatcher;
use Jinstan\Core\Event\EventListenerRegistrar;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\I18n\TranslationRegistrar;
use Jinstan\Core\I18n\TranslationCatalogLoader;
use Jinstan\Core\I18n\TranslationRegistry;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Security\CsrfTokenProvider;
use Jinstan\Core\Security\RateLimiter;
use Jinstan\Core\Infrastructure\Filesystem\Security\FileRateLimiter;
use Jinstan\Core\Infrastructure\Filesystem\Update\MaintenanceMode;
use Jinstan\Core\Infrastructure\Filesystem\Http\LocalResponseFileReader;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class FoundationServiceProvider implements ServiceProvider
{
    public function register(Application $application): void
    {
        $configLoader = new PhpConfigLoader();
        $config = new Config($configLoader->loadProject($application->projectPath()));
        $application->container()->set(Config::class, $config);

        $loggerFactory = new LoggerFactory();
        $logger = $loggerFactory->create($application->projectPath('storage/logs/app.log'));
        $application->container()->set(Logger::class, $logger);
        $application->container()->set(LoggerInterface::class, $logger);
        $router = new Router($application->container());
        $routeFiles = new LocalRouteFileLoader();
        $application->container()->set(Router::class, $router);
        $application->container()->set(RouteRegistrar::class, new RouteRegistrar($router, $routeFiles));
        $application->container()->set(UrlGenerator::class, new UrlGenerator($router));
        $responseFiles = new LocalResponseFileReader();
        $application->container()->set(ResponseFactory::class, new ResponseFactory($responseFiles));
        $events = new EventDispatcher();
        $application->container()->set(EventDispatcher::class, $events);
        $application->container()->set(EventListenerRegistrar::class, $events);
        $catalogs = new PhpTranslationCatalogLoader();
        $translations = new TranslationRegistry($catalogs);
        $application->container()->set(TranslationCatalogLoader::class, $catalogs);
        $application->container()->set(PhpTranslationCatalogLoader::class, $catalogs);
        $application->container()->set(TranslationRegistry::class, $translations);
        $application->container()->set(TranslationRegistrar::class, $translations);
        $csrf = new CsrfToken();
        $application->container()->set(CsrfToken::class, $csrf);
        $application->container()->set(CsrfTokenProvider::class, $csrf);
        $rateLimiter = new FileRateLimiter($application->projectPath('storage/cache/rate-limits'));
        $application->container()->set(FileRateLimiter::class, $rateLimiter);
        $application->container()->set(RateLimiter::class, $rateLimiter);
        $maintenance = new MaintenanceMode($application->projectPath('storage/maintenance.lock'));
        $application->container()->set(MaintenanceMode::class, $maintenance);
        $application->container()->set(MaintenanceModeGateway::class, $maintenance);
        $application->container()->set(\Jinstan\Core\Infrastructure\Filesystem\Update\MaintenanceMode::class, $maintenance);
    }

    public function boot(Application $application): void
    {
    }
}
