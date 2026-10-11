<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Installer\Services\InstallApplicationService;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Infrastructure\System\EnvironmentChecker;
use Jinstan\Core\Http\Controllers\Installer\InstallController;
use Jinstan\Core\Application\Installer\InstallationInput;
use Jinstan\Core\Infrastructure\Persistence\Installer\InstallationSeeder;
use Jinstan\Core\Infrastructure\Filesystem\Installer\InstallationLock;
use Jinstan\Core\Infrastructure\Filesystem\Installer\DatabaseConfigWriter;
use Jinstan\Core\Infrastructure\View\InstallerViewRenderer;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Providers\InstallerServiceProvider;
use Jinstan\Tests\Support\InstalledTestProject;
use Jinstan\Tests\Support\TestUrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\View\Presenters\Installer\InstallPresenter;
use PHPUnit\Framework\TestCase;

final class InstallerServiceProviderTest extends TestCase
{
    public function testItRegistersInstallerServicesWithoutAConfiguredDatabase(): void
    {
        $project = InstalledTestProject::create(false);
        $application = new Application($project->path, 'testing');
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->register(new InstallerServiceProvider());
        $application->boot();

        $container = $application->container();
        self::assertInstanceOf(InstallationLock::class, $container->get(InstallationLock::class));
        self::assertInstanceOf(EnvironmentChecker::class, $container->get(EnvironmentChecker::class));
        self::assertInstanceOf(InstallationInput::class, $container->get(InstallationInput::class));
        self::assertInstanceOf(InstallationSeeder::class, $container->get(InstallationSeeder::class));
        self::assertInstanceOf(DatabaseConfigWriter::class, $container->get(DatabaseConfigWriter::class));
        self::assertInstanceOf(InstallApplicationService::class, $container->get(InstallApplicationService::class));
        self::assertInstanceOf(InstallerViewRenderer::class, $container->get(InstallerViewRenderer::class));
        self::assertInstanceOf(InstallPresenter::class, $container->get(InstallPresenter::class));
        $html = (new InstallPresenter(
            new CsrfToken(),
            new EnvironmentChecker(),
            TestUrlGenerator::with(['install.store' => '/install']),
            new InstallerViewRenderer(
                $project->path . '/core/resources/views/installer',
                $project->path . '/storage/cache/latte-installer-test',
            ),
        ))->form('<unsafe>');
        self::assertStringContainsString('&lt;unsafe&gt;', $html);
        self::assertStringNotContainsString('<unsafe>', $html);
        self::assertStringContainsString('minlength="6"', $html);
        self::assertInstanceOf(InstallController::class, $container->get(InstallController::class));
        self::assertFalse($container->get(InstallationLock::class)->exists());
    }
}
