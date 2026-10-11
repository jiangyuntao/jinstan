<?php

declare(strict_types=1);

use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\Middleware\EnsureNotInstalledMiddleware;
use Jinstan\Core\Http\Controllers\Installer\InstallController;
use Jinstan\Core\Routing\RouteRegistrar;
use Psr\Http\Message\ResponseInterface;

return static function (
    RouteRegistrar $routes,
    ResponseFactory $responses,
    EnsureNotInstalledMiddleware $ensureNotInstalled,
): void {
    $routes->get('/health', static fn (): ResponseInterface => $responses->text('Jinstan is running.'))
        ->name('core.health');
    $routes->get('/', static fn (): ResponseInterface => $responses->text('Jinstan is running.'));
    $routes->group(['middleware' => [$ensureNotInstalled]], static function (RouteRegistrar $routes): void {
        $routes->get('/install', [InstallController::class, 'show'])
            ->name('install.show');
        $routes->post('/install', [InstallController::class, 'install'])
            ->name('install.store');
    });
};
