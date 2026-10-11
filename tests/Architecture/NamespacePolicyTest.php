<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class NamespacePolicyTest extends TestCase
{
    public function testCoreDoesNotProvidePreviousNamespaceAliasesOrWrappers(): void
    {
        $projectPath = dirname(__DIR__, 2);
        $violations = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectPath . '/core/src'),
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/\bclass_alias\s*\(/', $source) === 1) {
                $violations[] = str_replace($projectPath . '/', '', $file->getPathname());
            }
        }

        self::assertSame([], $violations, 'Core must expose only the current Namespace API.');
        self::assertFileDoesNotExist($projectPath . '/core/src/Auth/Authenticator.php');
        self::assertFileDoesNotExist($projectPath . '/core/src/Database/Connection.php');
        self::assertFileDoesNotExist($projectPath . '/core/src/Http/AdminAccess.php');
        self::assertFileDoesNotExist($projectPath . '/core/src/Installer/Installer.php');
        self::assertFileDoesNotExist($projectPath . '/core/src/Plugin/Plugin.php');
        self::assertFileDoesNotExist($projectPath . '/core/src/Plugin/PluginContext.php');
        self::assertFileDoesNotExist($projectPath . '/core/src/Update/CoreUpdater.php');
    }
}
