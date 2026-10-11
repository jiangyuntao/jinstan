<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\View;

use Jinstan\Core\Infrastructure\View\LocalViewPathResolver;
use Jinstan\Core\Infrastructure\View\NamespacedViewLoader;
use Jinstan\Core\View\ViewNamespaceRegistry;
use PHPUnit\Framework\TestCase;

final class ViewNamespaceRegistryTest extends TestCase
{
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->viewsPath = sys_get_temp_dir() . '/jinstan-view-namespace-' . bin2hex(random_bytes(4));
        mkdir($this->viewsPath . '/partials', 0750, true);
        file_put_contents($this->viewsPath . '/page.jst', 'page');
        file_put_contents($this->viewsPath . '/partials/action.jst', 'action');
    }

    public function testItResolvesRegisteredViewsAndRelativeReferences(): void
    {
        $views = new ViewNamespaceRegistry(new LocalViewPathResolver());
        $views->register('admin', $this->viewsPath);
        $loader = new NamespacedViewLoader($views);

        self::assertSame('page', $loader->getContent('admin::page.jst'));
        self::assertSame('admin::partials/action.jst', $loader->getReferredName('action.jst', 'admin::partials/form.jst'));
        self::assertSame('admin::page.jst', $loader->getReferredName('../page.jst', 'admin::partials/form.jst'));
    }

    public function testItRejectsDirectoryTraversal(): void
    {
        $views = new ViewNamespaceRegistry(new LocalViewPathResolver());
        $views->register('admin', $this->viewsPath);

        $this->expectException(\InvalidArgumentException::class);
        $views->resolve('admin::../secret.jst');
    }

    public function testItRejectsNamespaceReplacement(): void
    {
        $other = sys_get_temp_dir() . '/jinstan-other-views-' . bin2hex(random_bytes(4));
        mkdir($other, 0750, true);
        $views = new ViewNamespaceRegistry(new LocalViewPathResolver());
        $views->register('admin', $this->viewsPath);

        $this->expectException(\LogicException::class);
        $views->register('admin', $other);
    }
}
