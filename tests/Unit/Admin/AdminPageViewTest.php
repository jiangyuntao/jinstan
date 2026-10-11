<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Admin;

use Jinstan\Core\View\AdminPageView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminPageViewTest extends TestCase
{
    public function testItCarriesExplicitTemplateTitleAndData(): void
    {
        $view = new AdminPageView('plugin-settings', '插件设置', ['plugin' => ['name' => 'example']]);

        self::assertSame('plugin-settings', $view->template);
        self::assertSame('插件设置', $view->title);
        self::assertSame('example', $view->data['plugin']['name']);
        self::assertSame(
            'example-plugin::settings.jst',
            (new AdminPageView('example-plugin::settings.jst', 'Plugin'))->template,
        );
    }

    #[DataProvider('invalidTemplates')]
    public function testItRejectsUnsafeTemplateNames(string $template): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AdminPageView($template, 'Invalid');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTemplates(): iterable
    {
        yield 'directory traversal' => ['../secret'];
        yield 'extension injection' => ['users.jst'];
        yield 'absolute path' => ['/users'];
        yield 'empty name' => [''];
    }
}
