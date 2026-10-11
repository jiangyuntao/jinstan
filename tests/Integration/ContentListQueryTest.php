<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Queries\ContentListQuery;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class ContentListQueryTest extends TestCase
{
    public function testItFiltersVisibleColumnsAndPaginatesWithLocaleFallback(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $contents = new ContentRepository($connection);
        $first = $contents->create(new Content(0, 'article', null, 'draft', null, null, []), new ContentTranslation('en', 'First', 'alpha', null, []));
        $second = $contents->create(new Content(0, 'article', null, 'draft', null, null, []), new ContentTranslation('en', 'Second', 'beta', null, []));
        $third = $contents->create(new Content(0, 'article', null, 'draft', null, null, []), new ContentTranslation('zh-CN', '第三', 'gamma', null, []));
        $contents->setPublished($second->id, true);
        $query = new ContentListQuery($contents, $settings);

        $page = $query->list('article', 'ko', '', '', 2, ['id', 'title'], '未翻译', 2);
        self::assertSame(3, $page['total']);
        self::assertSame(2, $page['pages']);
        self::assertSame([$first->id], array_column($page['items'], 'id'));
        self::assertSame('en', $page['items'][0]['sourceLocale']);
        self::assertFalse($page['items'][0]['translated']);
        self::assertSame([$third->id, $second->id], array_column($query->list('article', 'ko', '', '', 1, ['title'], '未翻译', 2)['items'], 'id'));
        self::assertSame([$second->id], array_column($query->list('article', 'ko', '', 'published', 99, ['title'], '未翻译', 2)['items'], 'id'));

        self::assertSame(0, $query->list('article', 'ko', 'beta', '', 1, ['title'], '未翻译')['total']);
        self::assertSame(1, $query->list('article', 'ko', 'Second', 'published', 1, ['title'], '未翻译')['total']);
        self::assertSame($third->id, $query->list('article', 'ko', '第三', '', 1, ['title'], '未翻译')['items'][0]['id']);
    }
}
