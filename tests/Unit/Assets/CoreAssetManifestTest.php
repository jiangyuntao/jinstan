<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Assets;

use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifest;
use PHPUnit\Framework\TestCase;

final class CoreAssetManifestTest extends TestCase
{
    public function testManifestResolvesVersionedAssetUrlsAndMatchesPublishedFiles(): void
    {
        $root = dirname(__DIR__, 3);
        $manifest = CoreAssetManifest::load($root . '/core');

        self::assertSame('/assets/core/1.0.0/admin.css', $manifest->url('admin.css'));
        foreach ($manifest->files() as $path => $hash) {
            $file = $root . '/public/assets/core/' . $manifest->release() . '/' . $path;
            self::assertFileExists($file);
            self::assertSame($hash, hash_file('sha256', $file));
        }
    }
}
