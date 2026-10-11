<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTermListQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTranslationResolver;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use PHPUnit\Framework\TestCase;

final class TaxonomyTermListQueryTest extends TestCase
{
    public function testItUsesPreferredSourceTranslationForAnUntranslatedTerm(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en,zh');
        $taxonomies = new TaxonomyRepository($connection);
        $taxonomies->createTaxonomy('brand', '品牌');
        $termId = $taxonomies->createTerm('brand', 'en', 'Apple', 'apple');
        $translations = new TaxonomyTranslationResolver($taxonomies, $settings);

        $data = (new TaxonomyTermListQuery($taxonomies, $settings, $translations))->execute('brand', [
            'locale' => 'zh',
            'q' => ['invalid'],
            'page' => 'invalid',
        ]);

        self::assertSame('品牌', $data['group']['label']);
        self::assertSame('', $data['search']);
        self::assertSame('zh', $data['locale']);
        self::assertSame(1, $data['result']['page']);
        self::assertSame($termId, (int) $data['result']['items'][0]['id']);
        self::assertFalse($data['result']['items'][0]['translated']);
        self::assertSame('Apple', $data['result']['items'][0]['name']);
        self::assertSame('apple', $data['result']['items'][0]['slug']);
        self::assertSame('English (en)', $data['result']['items'][0]['sourceLocale']);
    }
}
