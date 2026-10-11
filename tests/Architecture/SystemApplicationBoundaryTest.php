<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SystemApplicationBoundaryTest extends TestCase
{
    public function testCacheAndLogUseCasesDependOnRuntimeStorageContract(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Application/System';
        $source = (string) file_get_contents($root . '/Queries/CacheLogsQuery.php')
            . (string) file_get_contents($root . '/Services/CacheLogsMaintenance.php');

        self::assertStringContainsString('RuntimeStorageGateway', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('string|RuntimeStorage', $source);
    }
}
