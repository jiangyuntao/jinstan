<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class InstallerApplicationBoundaryTest extends TestCase
{
    public function testInstallApplicationServiceDependsOnInstallerContracts(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Application/Installer/Services/InstallApplicationService.php',
        );

        self::assertStringContainsString('InstallationDatabaseGateway', $source);
        self::assertStringContainsString('InstallationLockGateway', $source);
        self::assertStringContainsString('DatabaseConfigGateway', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Database\\Connection', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Http', $source);
    }

    public function testInstallerHttpAndPresenterDependOnEnvironmentContract(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src';
        $sources = (string) file_get_contents($root . '/Http/Controllers/Installer/InstallController.php')
            . (string) file_get_contents($root . '/View/Presenters/Installer/InstallPresenter.php');

        self::assertStringContainsString('EnvironmentGateway', $sources);
        self::assertStringContainsString('InstallerView', $sources);
        self::assertStringNotContainsString('Infrastructure\\System\\EnvironmentChecker', $sources);
        self::assertStringNotContainsString('Infrastructure\\View\\InstallerViewRenderer', $sources);
        self::assertStringNotContainsString('new ResponseFactory', $sources);
    }
}
