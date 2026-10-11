<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Config\Config;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Foundation\Application;

final class DatabaseServiceProvider implements ServiceProvider
{
    public function register(Application $application): void
    {
        $container = $application->container();
        $connections = new ConnectionFactory();

        $container->set(ConnectionFactory::class, $connections);
        $migrations = new MigrationRunner();
        $container->set(MigrationRunner::class, $migrations);

        $database = $container->get(Config::class)->get('database', []);
        if (is_array($database) && $database !== []) {
            $connection = $connections->create($database);
            $container->set(Connection::class, $connection);
        }
    }

    public function boot(Application $application): void
    {
    }
}
