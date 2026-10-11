<?php

declare(strict_types=1);

namespace Jinstan\Core\Bootstrap;

use Jinstan\Core\Foundation\Application as FoundationApplication;
use Jinstan\Core\Http\Application as HttpApplication;
use Jinstan\Core\Providers\AdminServiceProvider;
use Jinstan\Core\Providers\ContentServiceProvider;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Providers\InstallerServiceProvider;
use Jinstan\Core\Providers\PluginServiceProvider;
use Jinstan\Core\Providers\RouteServiceProvider;
use Jinstan\Core\Providers\ThemeServiceProvider;

final class Bootstrap
{
    public static function application(string $projectPath): FoundationApplication
    {
        $application = new FoundationApplication($projectPath, self::environment());
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->register(new InstallerServiceProvider());
        $application->register(new ContentServiceProvider());
        $application->register(new ThemeServiceProvider());
        $application->register(new AdminServiceProvider());
        $application->register(new PluginServiceProvider());
        $application->register(new RouteServiceProvider());
        $application->boot();

        return $application;
    }

    public static function create(string $projectPath): HttpApplication
    {
        return self::application($projectPath)->container()->get(HttpApplication::class);
    }

    private static function environment(): string
    {
        $environment = $_ENV['APP_ENV'] ?? 'production';

        return is_string($environment) && trim($environment) !== ''
            ? trim($environment)
            : 'production';
    }
}
