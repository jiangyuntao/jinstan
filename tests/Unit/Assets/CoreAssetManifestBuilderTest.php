<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Assets;

use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifest;
use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifestBuilder;
use PHPUnit\Framework\TestCase;

final class CoreAssetManifestBuilderTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/jinstan-asset-builder-' . bin2hex(random_bytes(6));
        mkdir($this->workspace . '/core/src', 0755, true);
        mkdir($this->workspace . '/public/assets/core/2.0.0/tabler-icons/fonts', 0755, true);
        mkdir($this->workspace . '/public/assets/core/2.0.0/chart.js', 0755, true);
        file_put_contents(
            $this->workspace . '/core/src/Version.php',
            "<?php final class Version { public const CURRENT = '2.0.0'; }",
        );

        foreach ([
            'admin.css',
            'alpine.min.js',
            'chart.js/chart.umd.min.js',
            'codeflask.min.js',
            'tabler-icons/tabler-icons.min.css',
            'tabler-icons/fonts/tabler-icons.woff2',
        ] as $file) {
            file_put_contents($this->workspace . '/public/assets/core/2.0.0/' . $file, 'contents-' . $file);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testBuilderGeneratesLoadableManifestWithoutNodeDependencies(): void
    {
        (new CoreAssetManifestBuilder())->build(
            $this->workspace . '/core',
            $this->workspace . '/public',
        );

        $manifest = CoreAssetManifest::load($this->workspace . '/core');
        self::assertSame('2.0.0', $manifest->release());
        self::assertSame('/assets/core/2.0.0/alpine.min.js', $manifest->url('alpine.js'));
        self::assertSame('/assets/core/2.0.0/chart.js/chart.umd.min.js', $manifest->url('charts.js'));
        self::assertSame(
            hash('sha256', 'contents-admin.css'),
            $manifest->files()['admin.css'],
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
