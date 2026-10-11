<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class InfrastructureBoundaryTest extends TestCase
{
    public function testRuntimeIoRemainsInsideInfrastructure(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src';
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $ioPattern = '/\b(?:file_get_contents|file_put_contents|glob|is_file|is_dir|realpath|mkdir|rename|unlink|copy|hash_file)\s*\(/';
        $implementationPattern = '/\b(?:new\s+PDO|new\s+Latte|new\s+Logger|new\s+StreamHandler)\b/';

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, '/Infrastructure/')) {
                continue;
            }

            $source = (string) file_get_contents($path);
            if (preg_match($ioPattern, $source) === 1 || preg_match($implementationPattern, $source) === 1) {
                $violations[] = str_replace(dirname(__DIR__, 2) . '/', '', $path);
            }
        }

        self::assertSame([], $violations);
    }
}
