<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Queries\ContentEditQuery;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class ContentEditQueryTest extends TestCase
{
    public function testItMergesSharedAndCurrentLocaleFieldsWithoutUsingFallbackText(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $contents = new ContentRepository($connection);
        $content = $contents->create(
            new Content(0, 'page', null, 'draft', 'custom', null, ['shared' => 'value']),
            new ContentTranslation('en', 'English', 'english', null, ['localized' => 'hello']),
        );
        $contents->saveTranslation($content->id, new ContentTranslation('ko', '한국어', 'korean', null, ['localized' => '안녕']));
        $query = new ContentEditQuery($contents);

        self::assertNull($query->formInput('article', $content->id, 'en'));
        self::assertNull($query->formInput('page', $content->id + 100, 'en'));
        self::assertSame([
            'locale' => 'ko',
            'template' => 'custom',
            'title' => '한국어',
            'slug' => 'korean',
            'published' => false,
            'parent_id' => null,
            'data' => ['shared' => 'value', 'localized' => '안녕'],
        ], $query->formInput('page', $content->id, 'ko'));
        self::assertSame('', $query->formInput('page', $content->id, 'zh-CN')['title']);
        self::assertSame(['shared' => 'value'], $query->formInput('page', $content->id, 'zh-CN')['data']);
    }
}
