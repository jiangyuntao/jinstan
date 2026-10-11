<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RoutingBoundaryTest extends TestCase
{
    public function testRouteRegistrarDependsOnFileLoaderContract(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Routing/RouteRegistrar.php',
        );

        self::assertStringContainsString('RouteFileLoader', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('new LocalRouteFileLoader', $source);
    }
}
