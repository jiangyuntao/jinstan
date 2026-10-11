<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DatabaseApplicationBoundaryTest extends TestCase
{
    public function testDatabaseUseCasesDependOnOperationsAndBackupContracts(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Application';
        $source = (string) file_get_contents($root . '/Dashboard/Queries/DashboardQuery.php')
            . (string) file_get_contents($root . '/Database/Queries/DatabaseToolsQuery.php')
            . (string) file_get_contents($root . '/Database/Services/DatabaseMaintenance.php');

        self::assertStringContainsString('DatabaseOperations', $source);
        self::assertStringContainsString('BackupGateway', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Database\\Connection', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('PRAGMA integrity_check', $source);
        self::assertStringNotContainsString('OPTIMIZE TABLE', $source);
    }
}
