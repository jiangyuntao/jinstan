<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Tests\Support\InstalledTestProject;
use PHPUnit\Framework\TestCase;

final class DatabaseServiceProviderTest extends TestCase
{
    public function testItRegistersDatabaseInfrastructureWithoutOpeningAnUnconfiguredConnection(): void
    {
        $application = $this->application(InstalledTestProject::create(false));
        $container = $application->container();

        self::assertInstanceOf(ConnectionFactory::class, $container->get(ConnectionFactory::class));
        self::assertInstanceOf(MigrationRunner::class, $container->get(MigrationRunner::class));
        self::assertFalse($container->has(Connection::class));
    }

    public function testItRegistersConfiguredConnectionForAnInstalledProject(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $connection = $application->container()->get(Connection::class);

        self::assertInstanceOf(Connection::class, $connection);
        self::assertSame(1, (int) $connection->fetchOne('SELECT 1'));
    }

    private function application(InstalledTestProject $project): Application
    {
        $application = new Application($project->path, 'testing');
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->boot();

        return $application;
    }
}
