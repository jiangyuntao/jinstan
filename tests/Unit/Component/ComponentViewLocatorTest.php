<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Component;

use Jinstan\Core\Infrastructure\View\ComponentViewLocator;
use PHPUnit\Framework\TestCase;

final class ComponentViewLocatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/jinstan-component-views-' . bin2hex(random_bytes(4));
        foreach (['theme/components', 'plugins/z-plugin/views/components', 'plugins/a-plugin/views/components', 'core'] as $path) {
            mkdir($this->root . '/' . $path, 0750, true);
        }
    }

    public function testThemeOverridesPluginAndCoreViews(): void
    {
        $core = $this->view('core/example.jst');
        $plugin = $this->view('plugins/a-plugin/views/components/example.jst');
        $theme = $this->view('theme/components/example.jst');
        $locator = $this->locator();

        self::assertSame($theme, $locator->locate('example'));
        unlink($theme);
        self::assertSame($plugin, $locator->locate('example'));
        unlink($plugin);
        self::assertSame($core, $locator->locate('example'));
    }

    public function testPluginFallbackOrderIsStable(): void
    {
        $later = $this->view('plugins/z-plugin/views/components/example.jst');
        $first = $this->view('plugins/a-plugin/views/components/example.jst');

        self::assertNotSame($later, $first);
        self::assertSame($first, $this->locator()->locate('example'));
    }

    public function testItRejectsUnsafeComponentNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->locator()->locate('../example');
    }

    public function testItReportsMissingViews(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->locator()->locate('missing');
    }

    public function testDefaultCorePathStillResolvesAfterInfrastructureMove(): void
    {
        $locator = new ComponentViewLocator($this->root . '/theme', $this->root . '/plugins');

        self::assertSame(
            dirname(__DIR__, 3) . '/core/resources/views/components/latest-content.jst',
            $locator->locate('latest-content'),
        );
    }

    private function locator(): ComponentViewLocator
    {
        return new ComponentViewLocator(
            $this->root . '/theme',
            $this->root . '/plugins',
            $this->root . '/core',
        );
    }

    private function view(string $path): string
    {
        $path = $this->root . '/' . $path;
        file_put_contents($path, $path);

        return $path;
    }
}
