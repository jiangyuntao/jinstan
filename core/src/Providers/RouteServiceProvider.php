<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Application\Installer\InstallationLockGateway;
use Jinstan\Core\Application\Update\MaintenanceModeGateway;
use Closure;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Config\Config;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Http\Application as HttpApplication;
use Jinstan\Core\Http\ExceptionHandler;
use Jinstan\Core\Http\Kernel;
use Jinstan\Core\Http\Middleware\AdminFormFeedbackMiddleware;
use Jinstan\Core\Http\Middleware\AdminSecurityMiddleware;
use Jinstan\Core\Http\Middleware\AdminLocaleMiddleware;
use Jinstan\Core\Http\Middleware\EnsureNotInstalledMiddleware;
use Jinstan\Core\Http\Middleware\MaintenanceModeMiddleware;
use Jinstan\Core\Http\Middleware\AuthenticateMiddleware;
use Jinstan\Core\Http\Middleware\RequirePermissionMiddleware;
use Jinstan\Core\Http\Middleware\StartSessionMiddleware;
use Jinstan\Core\Http\Middleware\SecurityHeadersMiddleware;
use Jinstan\Core\Http\Middleware\VerifyCsrfTokenMiddleware;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\RouterRequestHandler;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\I18n\AdminLocale;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Support\Container;
use Jinstan\Core\Http\Controllers\Frontend\PublicContentController;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class RouteServiceProvider implements ServiceProvider
{
    private ?Closure $notFoundHandler = null;

    public function register(Application $application): void
    {
        $container = $application->container();
        $responses = $container->get(ResponseFactory::class);
        $routes = $container->get(RouteRegistrar::class);
        $ensureNotInstalled = new EnsureNotInstalledMiddleware(
            $container->get(InstallationLockGateway::class),
            $responses,
        );
        $container->set(EnsureNotInstalledMiddleware::class, $ensureNotInstalled);
        $routes->load($application->projectPath('core/routes/install.php'), $responses, $ensureNotInstalled);

        if (!$container->has(Connection::class)) {
            return;
        }

        $router = $container->get(Router::class);
        $public = $container->get(PublicContentController::class);
        $this->notFoundHandler = static function ($request) use ($public, $responses): ResponseInterface {
            $path = $request->getUri()->getPath();
            if (preg_match('~^/(?:admin(?:/|$)|login(?:/|$)|install(?:/|$))~', $path) === 1) {
                return $responses->text('Not Found', 404);
            }

            return $public->notFound($request);
        };

        $routes->load($application->projectPath('core/routes/web.php'), 'before_auth');
        $routes->load($application->projectPath('core/routes/auth.php'));
        $routes->load($application->projectPath('core/routes/web.php'), 'after_auth');
        $authenticate = new AuthenticateMiddleware(
            $container->get(Authenticator::class),
            $responses,
            $container->get(UrlGenerator::class),
        );
        $container->set(AuthenticateMiddleware::class, $authenticate);
        $verifyCsrf = new VerifyCsrfTokenMiddleware($container->get(CsrfToken::class));
        $container->set(VerifyCsrfTokenMiddleware::class, $verifyCsrf);
        $routes->load(
            $application->projectPath('core/routes/admin.php'),
            'before_plugins',
            null,
            null,
            new RequirePermissionMiddleware(
                $container->get(Authenticator::class),
                $responses,
                'content.manage',
            ),
            $authenticate,
            $verifyCsrf,
        );
    }

    public function boot(Application $application): void
    {
        $container = $application->container();
        $router = $container->get(Router::class);

        if ($container->has(Connection::class)) {
            $this->registerRoutesAfterPlugins($application, $container, $router);
        }

        $config = $container->get(Config::class);
        $exceptions = new ExceptionHandler(
            $container->get(LoggerInterface::class),
            (bool) $config->get('app.debug'),
            $container->get(ResponseFactory::class),
        );
        $maintenance = new MaintenanceModeMiddleware(
            $container->get(MaintenanceModeGateway::class),
            $container->get(ResponseFactory::class),
            ['/health'],
        );
        $container->set(MaintenanceModeMiddleware::class, $maintenance);
        $startSession = new StartSessionMiddleware();
        $container->set(StartSessionMiddleware::class, $startSession);
        $securityHeaders = new SecurityHeadersMiddleware($exceptions);
        $container->set(SecurityHeadersMiddleware::class, $securityHeaders);
        $middleware = [$securityHeaders, $maintenance, $startSession];
        if ($container->has(Connection::class)) {
            $adminLocale = new AdminLocaleMiddleware(
                $container->get(SettingsReadRepository::class),
                $container->get(AdminLocale::class),
            );
            $container->set(AdminLocaleMiddleware::class, $adminLocale);
            $middleware[] = $adminLocale;
            $middleware[] = new AdminSecurityMiddleware(
                $container->get(Authenticator::class),
                $container->get(CsrfToken::class),
                $container->get(ResponseFactory::class),
                $container->get(UrlGenerator::class),
            );
        }
        $middleware[] = new AdminFormFeedbackMiddleware();
        $kernel = new Kernel(
            new RouterRequestHandler($router, $this->notFoundHandler),
            $exceptions,
            $middleware,
        );
        $container->set(ExceptionHandler::class, $exceptions);
        $container->set(Kernel::class, $kernel);
        $container->set(
            HttpApplication::class,
            new HttpApplication($kernel),
        );
    }

    private function registerRoutesAfterPlugins(Application $application, Container $container, Router $router): void
    {
        $responses = $container->get(ResponseFactory::class);
        $urls = $container->get(UrlGenerator::class);
        $routes = $container->get(RouteRegistrar::class);
        $authenticate = $container->get(AuthenticateMiddleware::class);
        $verifyCsrf = $container->get(VerifyCsrfTokenMiddleware::class);
        $routes->load(
            $application->projectPath('core/routes/admin.php'),
            'after_plugins',
            $responses,
            $urls,
            null,
            $authenticate,
            $verifyCsrf,
            new RequirePermissionMiddleware(
                $container->get(Authenticator::class),
                $responses,
                'system.manage',
            ),
            new RequirePermissionMiddleware(
                $container->get(Authenticator::class),
                $responses,
                'users.manage',
            ),
            new RequirePermissionMiddleware(
                $container->get(Authenticator::class),
                $responses,
                'settings.manage',
            ),
        );

        $routes->load(
            $application->projectPath('core/routes/web.php'),
            'content',
            $container->get(ContentTypeRegistry::class),
            $container->get(PublicContentController::class),
        );
        $routes->load($application->projectPath('core/routes/admin.php'), 'after_content', $responses, $urls, null, $authenticate, $verifyCsrf);
    }
}
