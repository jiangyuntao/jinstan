<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class HttpResponseBoundaryTest extends TestCase
{
    public function testOnlyResponseFactoryDependsOnConcretePsr7Response(): void
    {
        $sourceRoot = dirname(__DIR__, 2) . '/core/src';
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $file->getPathname() === $sourceRoot . '/Http/ResponseFactory.php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (is_string($source) && str_contains($source, 'Nyholm\\Psr7\\Response')) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame([], $violations, 'Core classes must create responses through Http\\ResponseFactory.');
    }

    public function testResponseFactoryDependsOnFileReaderContract(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Http/ResponseFactory.php',
        );

        self::assertStringContainsString('ResponseFileReader', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('new LocalResponseFileReader', $source);
    }

    public function testStateMiddlewaresDependOnLockContracts(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Http/Middleware';
        $source = (string) file_get_contents($root . '/EnsureNotInstalledMiddleware.php')
            . (string) file_get_contents($root . '/MaintenanceModeMiddleware.php');

        self::assertStringContainsString('InstallationLockGateway', $source);
        self::assertStringContainsString('MaintenanceModeGateway', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
    }

    public function testExceptionHandlerReceivesResponseFactory(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Http/ExceptionHandler.php',
        );

        self::assertStringNotContainsString('new ResponseFactory', $source);
        self::assertStringNotContainsString('?ResponseFactory', $source);
    }
}
