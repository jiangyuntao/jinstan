<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class TaxonomyListingTest extends TestCase
{
    public function testGroupsAreFilteredAndPaginatedBeforeRendering(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-taxonomy-' . bin2hex(random_bytes(6));
        mkdir($path, 0750, true);
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $path . '/db.sqlite']);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $repository = new TaxonomyRepository($db);
        $repository->createTaxonomy('brand', '品牌');
        $repository->createTaxonomy('tag', '标签');
        for ($i = 1; $i <= 23; ++$i) {
            $repository->createTerm('brand', 'en', sprintf('Brand %02d', $i), 'brand-' . $i);
        }
        $repository->createTerm('tag', 'en', 'Other group', 'other');
        $repository->createTerm('tag', 'en', '100% match', 'percent');
        $first = $repository->termPage('brand');
        self::assertSame(23, $first['total']);
        self::assertCount(20, $first['items']);
        self::assertCount(3, $repository->termPage('brand', '', 2)['items']);
        $repository->reorder('brand', array_reverse(array_column($first['items'], 'id')), 1);
        self::assertSame('Brand 20', $repository->termPage('brand')['items'][0]['name']);
        self::assertSame(1, $repository->termPage('brand', 'brand-23')['total']);
        self::assertSame(0, $repository->termPage('brand', 'Other')['total']);
        self::assertSame(1, $repository->termPage('tag', '%')['total']);
        self::assertCount(20, $repository->search('brand', ''));
        $renderedResult = $first;
        foreach ($renderedResult['items'] as &$item) {
            $item['translated'] = true;
            $item['sourceLocale'] = null;
        }
        unset($item);
        $html = (new AdminViewRenderer(dirname(__DIR__, 2) . '/core/resources/views/admin', $path . '/cache', TestUrlGenerator::adminViews()))->render('taxonomy-terms', '分类与标签', [
            'taxonomies' => $repository->taxonomies(),
            'selected' => 'brand',
            'selectedLabel' => '品牌',
            'query' => '',
            'context' => 'q=&page=1',
            'result' => $renderedResult,
            'previousUrl' => '?page=0',
            'nextUrl' => '?taxonomy=brand&page=2',
            'locale' => 'en',
            'locales' => ['en' => 'English'],
        ]);
        self::assertStringContainsString('Brand 01', $html);
        self::assertStringNotContainsString('Other group', $html);
        self::assertStringContainsString('下一页', $html);
        self::assertStringContainsString('name="taxonomy" value="brand"', $html);
        self::assertStringContainsString('>词汇表设置</a>', $html);
    }
}
