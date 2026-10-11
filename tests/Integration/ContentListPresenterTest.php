<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\ContentListPresenter;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class ContentListPresenterTest extends TestCase
{
    public function testItBuildsColumnsFallbackLabelsAndPaginationLinks(): void
    {
        $root = dirname(__DIR__, 2);
        $cache = sys_get_temp_dir() . '/jinstan-list-presenter-' . bin2hex(random_bytes(6));
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, $root . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $csrf = new CsrfToken();

        try {
            $views = new AdminViewRenderer($root . '/core/resources/views/admin', $cache, TestUrlGenerator::adminViews(), $csrf);
            $presenter = new ContentListPresenter($views, $csrf, $settings, TestUrlGenerator::contentAdmin());
            $visible = $presenter->visibleColumns(['columns' => ['status']]);
            self::assertSame(['id', 'title', 'status'], $visible);
            self::assertSame(['id', 'title', 'slug'], $presenter->visibleColumns(['columns' => [['nested'], 'slug']]));

            $data = $presenter->present('article', '文章', 'ko', '', 'published', $visible, [
                'items' => [['id' => 7, 'title' => 'Example', 'slug' => 'example', 'status' => 'published', 'translated' => false, 'sourceLocale' => 'en']],
                'total' => 11,
                'page' => 1,
                'pages' => 2,
                'offset' => 0,
            ]);

            self::assertSame(['id', 'title', 'status'], array_column(array_slice($data['columns'], 0, -1), 'name'));
            self::assertSame('English', $data['items'][0]['sourceLocaleName']);
            self::assertSame('/admin/content/article/7/edit?locale=ko', $data['items'][0]['editUrl']);
            self::assertSame('/admin/content/article/7/delete', $data['items'][0]['deleteUrl']);
            self::assertSame(2, count($data['pagination']['pages']));
            self::assertStringContainsString('page=2', $data['pagination']['nextUrl']);
            self::assertSame(10, $data['pagination']['to']);
        } finally {
            if (is_dir($cache)) {
                rmdir($cache);
            }
        }
    }
}
