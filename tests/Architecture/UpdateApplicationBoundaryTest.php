<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class UpdateApplicationBoundaryTest extends TestCase
{
    public function testCoreUpdaterDependsOnUpdateContracts(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Application/Update/Services/CoreUpdater.php',
        );

        self::assertStringContainsString('CoreMigrationGateway', $source);
        self::assertStringContainsString('CoreUpdateStorage', $source);
        self::assertStringContainsString('MaintenanceModeGateway', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Database\\Connection', $source);
    }
}
