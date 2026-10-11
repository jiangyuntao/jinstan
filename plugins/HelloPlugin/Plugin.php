<?php

declare(strict_types=1);

namespace Jinstan\Plugins\HelloPlugin;

use Jinstan\Core\Plugin\PluginRegistrar;
use Jinstan\Core\Plugin\PluginServiceProvider;

require_once __DIR__ . '/GreetingService.php';
require_once __DIR__ . '/HelloController.php';

final class Plugin implements PluginServiceProvider
{
    public function install(): void
    {
    }
    public function enable(): void
    {
    }
    public function disable(): void
    {
    }
    public function upgrade(string $fromVersion): void
    {
    }
    public function uninstall(): void
    {
    }

    public function register(PluginRegistrar $registrar): void
    {
        $greetings = new GreetingService();
        $registrar->services()->register($greetings);
        $registrar->services()->register(new HelloController($greetings, $registrar->responses()));
        $registrar->routes()->load(__DIR__ . '/routes/web.php');
    }

    public function boot(): void
    {
    }
}
