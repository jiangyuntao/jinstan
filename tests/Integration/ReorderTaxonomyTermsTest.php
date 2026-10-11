<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Taxonomy\Services\ReorderTaxonomyTerms;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReorderTaxonomyTermsTest extends TestCase
{
    private Connection $connection;

    private TaxonomyRepository $taxonomies;

    private ReorderTaxonomyTerms $command;

    protected function setUp(): void
    {
        $this->connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($this->connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $this->taxonomies = new TaxonomyRepository($this->connection);
        $this->taxonomies->createTaxonomy('topic', '主题');
        $this->command = new ReorderTaxonomyTerms($this->taxonomies);
    }

    public function testItValidatesAndPersistsTheOrderForTheRequestedPage(): void
    {
        $first = $this->taxonomies->createTerm('topic', 'en', 'First', 'first');
        $second = $this->taxonomies->createTerm('topic', 'en', 'Second', 'second');

        $context = $this->command->execute([
            'taxonomy' => ' topic ',
            'order' => [(string) $second, (string) $first],
            'page' => '2',
        ]);

        self::assertSame(['taxonomy' => 'topic', 'page' => 2], $context);
        self::assertSame(
            [210, 220],
            array_map(
                'intval',
                array_column($this->connection->fetchAllAssociative(
                    'SELECT position FROM taxonomy_term_positions WHERE term_id IN (?, ?) ORDER BY position',
                    [$first, $second],
                ), 'position'),
            ),
        );
        self::assertSame($second, (int) $this->connection->fetchOne(
            'SELECT term_id FROM taxonomy_term_positions ORDER BY position LIMIT 1',
        ));
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('invalidInputProvider')]
    public function testItRejectsInvalidOrderingInput(array $input): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->command->execute($input);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidInputProvider(): iterable
    {
        yield 'missing taxonomy' => [['order' => []]];
        yield 'non-array order' => [['taxonomy' => 'topic', 'order' => '1']];
        yield 'invalid id' => [['taxonomy' => 'topic', 'order' => ['no']]];
        yield 'duplicate ids' => [['taxonomy' => 'topic', 'order' => [1, 1]]];
    }
}
