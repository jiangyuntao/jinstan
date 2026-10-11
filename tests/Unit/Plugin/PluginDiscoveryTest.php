<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Plugin;

use Jinstan\Core\Infrastructure\Filesystem\Plugin\PluginDiscovery;
use PHPUnit\Framework\TestCase;

final class PluginDiscoveryTest extends TestCase
{
    public function testItDiscoversPluginManifestsInNameOrder(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-plugin-discovery-' . bin2hex(random_bytes(6));
        mkdir($path . '/second', 0750, true);
        mkdir($path . '/first', 0750, true);
        file_put_contents($path . '/second/plugin.json', '{"name":"second"}');
        file_put_contents($path . '/first/plugin.json', '{"name":"first"}');

        try {
            $plugins = (new PluginDiscovery())->discover($path);

            self::assertSame(['first', 'second'], array_column(array_column($plugins, 'manifest'), 'name'));
        } finally {
            unlink($path . '/first/plugin.json');
            unlink($path . '/second/plugin.json');
            rmdir($path . '/first');
            rmdir($path . '/second');
            rmdir($path);
        }
    }

    public function testItReturnsOnlyExistingOptionalDirectories(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-plugin-directory-' . bin2hex(random_bytes(6));
        mkdir($path . '/views', 0750, true);

        try {
            $discovery = new PluginDiscovery();

            self::assertSame($path . '/views', $discovery->optionalDirectory($path, 'views'));
            self::assertNull($discovery->optionalDirectory($path, 'lang/admin'));
        } finally {
            rmdir($path . '/views');
            rmdir($path);
        }
    }
}
