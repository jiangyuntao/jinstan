<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Theme;

use Jinstan\Core\Infrastructure\View\ContentBodyRenderer;
use PHPUnit\Framework\TestCase;

final class ContentBodyRendererTest extends TestCase
{
    public function testItRendersMarkdownWithoutUnsafeHtmlOrLinks(): void
    {
        $html = (new ContentBodyRenderer())->render(
            "**Safe** <script>alert('x')</script> [bad](javascript:alert('x'))",
        );

        self::assertStringContainsString('<strong>Safe</strong>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('href="javascript:', $html);
    }

    public function testItPreservesExplicitHtmlDocuments(): void
    {
        $document = '<html><body>Landing page</body></html>';

        self::assertSame($document, (new ContentBodyRenderer())->render([
            'format' => 'html_document',
            'value' => $document,
        ]));
    }
}
