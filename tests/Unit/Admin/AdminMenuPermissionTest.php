<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Admin;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class AdminMenuPermissionTest extends TestCase
{
    public function testItOnlyRendersAuthorizedMenuGroups(): void
    {
        $root = dirname(__DIR__, 3);
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $types->register(new ContentTypeDefinition('wiki', '知识库', [], 'wiki-detail', '/wiki/{slug}'));
        $renderer = new AdminViewRenderer(
            $root . '/core/resources/views/admin',
            sys_get_temp_dir() . '/jinstan-menu-' . bin2hex(random_bytes(5)),
            TestUrlGenerator::adminViews(),
            null,
            null,
            static fn (string $permission): bool => $permission === 'content.manage',
            $types,
        );

        $html = $renderer->render('profile', '测试', [
            'user' => ['email' => 'user@example.test'],
        ]);

        self::assertStringContainsString('>文章</a>', $html);
        self::assertStringContainsString('>知识库</a>', $html);
        self::assertStringNotContainsString('/admin/settings/system', $html);
        self::assertStringNotContainsString('/admin/database', $html);
        self::assertStringNotContainsString('/admin/users', $html);
    }
}
