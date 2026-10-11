<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Application\Installer\EnvironmentGateway;
use Jinstan\Core\Application\Installer\DatabaseConfigGateway;
use Jinstan\Core\Application\Installer\InstallationDatabaseGateway;
use Jinstan\Core\Application\Installer\InstallationInput;
use Jinstan\Core\Application\Installer\InstallationLockGateway;
use Jinstan\Core\Application\Installer\Services\InstallApplicationService;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Infrastructure\System\EnvironmentChecker;
use Jinstan\Core\Http\Controllers\Installer\InstallController;
use Jinstan\Core\Infrastructure\Persistence\Installer\InstallationSeeder;
use Jinstan\Core\Infrastructure\Persistence\Installer\InstallationDatabaseInstaller;
use Jinstan\Core\Infrastructure\Filesystem\Installer\InstallationLock;
use Jinstan\Core\Infrastructure\Filesystem\Installer\DatabaseConfigWriter;
use Jinstan\Core\Infrastructure\View\InstallerViewRenderer;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\View\Presenters\Installer\InstallPresenter;
use Jinstan\Core\View\InstallerView;

final class InstallerServiceProvider implements ServiceProvider
{
    public function register(Application $application): void
    {
        $container = $application->container();
        $lock = new InstallationLock($application->projectPath('storage/installed.php'));
        $input = new InstallationInput($application->projectPath());
        $seeder = new InstallationSeeder();
        $configWriter = new DatabaseConfigWriter($application->projectPath());
        $databaseInstaller = new InstallationDatabaseInstaller(
            $container->get(ConnectionFactory::class),
            $container->get(MigrationRunner::class),
            $seeder,
            $application->projectPath('core/src/Database/Migrations'),
        );
        $installApplication = new InstallApplicationService(
            $databaseInstaller,
            $lock,
            $input,
            $configWriter,
        );
        $environment = new EnvironmentChecker();
        $installViews = new InstallerViewRenderer(
            $application->projectPath('core/resources/views/installer'),
            $application->projectPath('storage/cache/latte-installer'),
        );
        $installPresenter = new InstallPresenter(
            $container->get(CsrfToken::class),
            $environment,
            $container->get(UrlGenerator::class),
            $installViews,
        );

        $container->set(InstallationLock::class, $lock);
        $container->set(InstallationLockGateway::class, $lock);
        $container->set(InstallationInput::class, $input);
        $container->set(InstallationSeeder::class, $seeder);
        $container->set(DatabaseConfigWriter::class, $configWriter);
        $container->set(DatabaseConfigGateway::class, $configWriter);
        $container->set(InstallationDatabaseGateway::class, $databaseInstaller);
        $container->set(InstallApplicationService::class, $installApplication);
        $container->set(EnvironmentChecker::class, $environment);
        $container->set(EnvironmentGateway::class, $environment);
        $container->set(InstallerViewRenderer::class, $installViews);
        $container->set(InstallerView::class, $installViews);
        $container->set(InstallPresenter::class, $installPresenter);
        $installController = new InstallController(
            $installApplication,
            $container->get(CsrfToken::class),
            $container->get(EnvironmentGateway::class),
            $installPresenter,
            $container->get(ResponseFactory::class),
        );
        $container->set(InstallController::class, $installController);
    }

    public function boot(Application $application): void
    {
    }
}
