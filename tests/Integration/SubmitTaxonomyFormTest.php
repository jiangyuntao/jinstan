<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Taxonomy\Services\SubmitTaxonomyForm;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use PHPUnit\Framework\TestCase;

final class SubmitTaxonomyFormTest extends TestCase
{
    public function testItCoordinatesTaxonomyAndTermWrites(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $taxonomies = new TaxonomyRepository($connection);
        $command = new SubmitTaxonomyForm($taxonomies, new ContentTypeRegistry());

        $command->execute(false, false, false, '', null, 'en', [
            'handle' => 'brand',
            'label' => '品牌',
        ], false);
        $command->execute(false, true, false, 'brand', null, 'en', ['label' => '制造商'], false);
        $command->execute(true, false, false, 'brand', null, 'en', [
            'name' => ' Apple ',
            'slug' => ' apple ',
        ], false);
        $termId = (int) $taxonomies->termPage('brand')['items'][0]['id'];
        $command->execute(true, true, false, 'brand', $termId, 'en', [
            'name' => 'Apple Inc.',
            'slug' => 'apple-inc',
        ], false);

        self::assertSame('制造商', $taxonomies->taxonomy('brand')['label']);
        self::assertSame('Apple Inc.', $taxonomies->term('brand', $termId, 'en')['name']);

        $command->execute(true, true, true, 'brand', $termId, 'en', [], true);
        $command->execute(false, true, true, 'brand', null, 'en', [], true);

        $this->expectException(\OutOfBoundsException::class);
        $taxonomies->taxonomy('brand');
    }

    public function testDeletionRequiresExplicitConfirmation(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $taxonomies = new TaxonomyRepository($connection);
        $taxonomies->createTaxonomy('brand', '品牌');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('请确认删除影响后再提交。');

        (new SubmitTaxonomyForm($taxonomies, new ContentTypeRegistry()))->execute(
            false,
            true,
            true,
            'brand',
            null,
            'en',
            [],
            false,
        );
    }
}
