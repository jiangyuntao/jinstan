<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Theme;

use Jinstan\Core\Infrastructure\Filesystem\Theme\ActiveThemeLocator;
use PHPUnit\Framework\TestCase;

final class ActiveThemeLocatorTest extends TestCase
{
    private string $themesPath;

    protected function setUp(): void
    {
        $this->themesPath = sys_get_temp_dir() . '/jinstan-theme-locator-' . bin2hex(random_bytes(6));
        mkdir($this->themesPath . '/default', 0750, true);
        mkdir($this->themesPath . '/custom', 0750);
    }

    protected function tearDown(): void
    {
        rmdir($this->themesPath . '/custom');
        rmdir($this->themesPath . '/default');
        rmdir($this->themesPath);
    }

    public function testItLocatesAThemeInsideTheThemeRoot(): void
    {
        self::assertSame(
            realpath($this->themesPath . '/custom'),
            (new ActiveThemeLocator($this->themesPath))->locate('custom'),
        );
    }

    public function testItFallsBackWhenTheHandleEscapesTheThemeRoot(): void
    {
        self::assertSame(
            $this->themesPath . '/default',
            (new ActiveThemeLocator($this->themesPath))->locate('../'),
        );
    }
}
