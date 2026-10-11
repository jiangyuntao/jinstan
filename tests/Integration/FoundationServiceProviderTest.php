<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Config\Config;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Infrastructure\Filesystem\Update\MaintenanceMode;
use Jinstan\Core\Infrastructure\Config\PhpConfigLoader;
use Jinstan\Core\Infrastructure\Logging\LoggerFactory;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Routing\RouteFileLoader;
use Jinstan\Core\Http\ResponseFileReader;
use Jinstan\Core\Infrastructure\Filesystem\Routing\LocalRouteFileLoader;
use Jinstan\Core\Infrastructure\Filesystem\Http\LocalResponseFileReader;
use Jinstan\Tests\Support\InstalledTestProject;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FoundationServiceProviderTest extends TestCase
{
    public function testItRegistersConfigurationAndLoggerBindings(): void
    {
        $project = InstalledTestProject::create(false);
        $application = new Application($project->path, 'testing');

        $application->register(new FoundationServiceProvider());
        $application->boot();

        $container = $application->container();
        self::assertInstanceOf(Config::class, $container->get(Config::class));
        self::assertFalse($container->has(PhpConfigLoader::class));
        self::assertInstanceOf(Logger::class, $container->get(Logger::class));
        self::assertFalse($container->has(LoggerFactory::class));
        self::assertSame($container->get(Logger::class), $container->get(LoggerInterface::class));
        self::assertInstanceOf(Router::class, $container->get(Router::class));
        self::assertInstanceOf(RouteRegistrar::class, $container->get(RouteRegistrar::class));
        self::assertInstanceOf(UrlGenerator::class, $container->get(UrlGenerator::class));
        self::assertFalse($container->has(RouteFileLoader::class));
        self::assertFalse($container->has(LocalRouteFileLoader::class));
        self::assertFalse($container->has(ResponseFileReader::class));
        self::assertFalse($container->has(LocalResponseFileReader::class));
        self::assertSame([], $container->get(Config::class)->get('database'));
    }
}
