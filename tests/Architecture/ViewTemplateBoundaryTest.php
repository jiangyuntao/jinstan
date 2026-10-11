<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ViewTemplateBoundaryTest extends TestCase
{
    private const RAW_OUTPUT_VIEWS = [
        'core/resources/views/admin/content-types.jst' => 1,
        'core/resources/views/admin/dashboard.jst' => 1,
        'core/resources/views/admin/fields/media.jst' => 1,
        'core/resources/views/admin/fields/parent-content.jst' => 1,
        'core/resources/views/admin/fields/sku.jst' => 1,
        'core/resources/views/admin/partials/content-taxonomy.jst' => 1,
    ];

    public function testAllProjectTemplatesUseJstExtension(): void
    {
        $legacy = [];
        foreach ($this->templateRoots() as $root) {
            foreach ($this->files($root) as $file) {
                if ($file->getExtension() === 'latte') {
                    $legacy[] = $this->relative($file->getPathname());
                }
            }
        }

        self::assertSame([], $legacy, 'Latte templates must use the .jst extension.');
    }

    public function testViewsDoNotAccessInfrastructureOrPerformWrites(): void
    {
        $violations = [];
        $forbidden = ['Container', 'PDO', 'Connection', 'file_put_contents(', 'unlink(', '->query(', '->executeStatement('];
        foreach ($this->templates() as $file) {
            $source = (string) file_get_contents($file);
            foreach ($forbidden as $needle) {
                if (str_contains($source, $needle)) {
                    $violations[] = $this->relative($file) . ': ' . $needle;
                }
            }
        }

        self::assertSame([], $violations, 'Views must not access infrastructure or perform writes.');
    }

    public function testViewNamespaceRegistryDependsOnPathResolverContract(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/View/ViewNamespaceRegistry.php',
        );

        self::assertStringContainsString('ViewPathResolver', $source);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $source);
        self::assertStringNotContainsString('new LocalViewPathResolver', $source);
    }

    public function testRawOutputIsRestrictedToReviewedJsonPayloads(): void
    {
        $rawOutputViews = [];
        foreach ($this->templates() as $file) {
            $source = (string) file_get_contents($file);
            $count = substr_count($source, '|noescape');
            if ($count > 0) {
                $rawOutputViews[$this->relative($file)] = $count;
            }
        }
        ksort($rawOutputViews, SORT_STRING);
        $expected = self::RAW_OUTPUT_VIEWS;
        ksort($expected, SORT_STRING);

        self::assertSame($expected, $rawOutputViews, 'New raw template output requires explicit review and an allowlist update.');
    }

    /** @return list<string> */
    private function templates(): array
    {
        $templates = [];
        foreach ($this->templateRoots() as $root) {
            foreach ($this->files($root) as $file) {
                if ($file->getExtension() === 'jst') {
                    $templates[] = $file->getPathname();
                }
            }
        }

        return $templates;
    }

    /** @return list<string> */
    private function templateRoots(): array
    {
        $root = dirname(__DIR__, 2);

        return [
            $root . '/core/resources/views',
            $root . '/plugins',
            $root . '/themes',
            $root . '/site',
        ];
    }

    /** @return \Generator<int, \SplFileInfo> */
    private function files(string $root): \Generator
    {
        if (!is_dir($root)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(dirname(__DIR__, 2), '', $path), '/');
    }
}
