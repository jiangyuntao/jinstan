<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Queries\ContentSearchQuery;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use PHPUnit\Framework\TestCase;

final class ContentSearchQueryTest extends TestCase
{
    public function testItSearchesOnlyConfiguredParentAndTaxonomySources(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $contents = new ContentRepository($connection);
        $taxonomy = new TaxonomyRepository($connection);
        $taxonomy->createTaxonomy('topic', 'Topic');
        $term = $taxonomy->createTerm('topic', 'en', 'News', 'news');
        $types = new ContentTypeRegistry();
        $types->register(new ContentTypeDefinition('page', 'Page', [], 'content-detail', '/{slug}', true, ['topic' => ['multiple' => true, 'required' => false]]));
        $types->register(new ContentTypeDefinition('article', 'Article', [], 'content-detail', '/blog/{slug}'));
        $first = $contents->create(new Content(0, 'page', null, 'draft', null, null, []), new ContentTranslation('en', 'Parent One', 'parent-one', null, []));
        $contents->create(new Content(0, 'page', null, 'draft', null, null, []), new ContentTranslation('en', 'Parent Two', 'parent-two', null, []));
        $query = new ContentSearchQuery($types, $contents, $taxonomy);

        self::assertSame([], $query->parents('page', 'en', ''));
        self::assertSame([$first->id], array_column($query->parents('page', 'en', 'One'), 'id'));
        self::assertSame([], $query->parents('page', 'en', 'One', $first->id));
        self::assertSame([$term], array_column($query->terms('page', 'topic', 'en', 'News'), 'id'));
        self::assertSame([], $query->terms('page', 'topic', 'en', null));

        try {
            $query->parents('article', 'en', 'Parent');
            self::fail('Expected non-hierarchical type to be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        try {
            $query->terms('page', 'unknown', 'en', 'News');
            self::fail('Expected unbound taxonomy to be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
    }
}
