<?php

declare(strict_types=1);

use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Plugins\HelloPlugin\HelloController;

return static function (RouteRegistrar $routes): void {
    $routes->get('/hello-plugin', [HelloController::class, 'index']);
};
