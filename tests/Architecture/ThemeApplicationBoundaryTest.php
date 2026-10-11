<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ThemeApplicationBoundaryTest extends TestCase
{
    public function testThemeUseCasesDependOnFileGateway(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Application/Theme';
        $source = (string) file_get_contents($root . '/Queries/ThemeManagerQuery.php')
            . (string) file_get_contents($root . '/Services/ThemeWriter.php');

        self::assertStringContainsString('ThemeFileGateway', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('ThemeFileManager', $source);
    }

    public function testSystemSettingsRequestDependsOnThemeSelectionGateway(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Http/Requests/Admin/SystemSettingsRequest.php',
        );

        self::assertStringContainsString('ThemeSelectionGateway', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('new ThemeFileManager', $source);
        self::assertStringNotContainsString('new TemplateCatalog', $source);
    }
}
