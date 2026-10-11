<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PluginApplicationBoundaryTest extends TestCase
{
    public function testPluginManagerDependsOnDiscoveryAndMigrationContracts(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Application/Plugin/Services/PluginManager.php',
        );

        self::assertStringContainsString('PluginDiscoveryGateway', $source);
        self::assertStringContainsString('PluginMigrationGateway', $source);
        self::assertStringContainsString('PluginViewRegistrar', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\View', $source);
        self::assertStringNotContainsString('new PluginDiscovery', $source);
    }
}
