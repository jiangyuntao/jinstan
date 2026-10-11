<?php

declare(strict_types=1);

use Jinstan\Core\Http\Controllers\Admin\LoginController;
use Jinstan\Core\Routing\RouteRegistrar;

return static function (RouteRegistrar $routes): void {
    $routes->get('/login', [LoginController::class, 'show'])
        ->name('login.show');
    $routes->post('/login', [LoginController::class, 'login'])
        ->name('login.store');
};
