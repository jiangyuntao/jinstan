<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\TaxonomyFormPresenter;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyFormQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTranslationResolver;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class TaxonomyFormPresentationTest extends TestCase
{
    public function testItBuildsAnUntranslatedTermFormWithASourceReference(): void
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

        $form = (new TaxonomyFormQuery($taxonomies))->execute(true, 'brand', $termId, 'zh');
        $view = (new TaxonomyFormPresenter(
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                sys_get_temp_dir() . '/jinstan-taxonomy-form-presenter',
                TestUrlGenerator::adminViews(),
            ),
            $settings,
            $translations,
        ))->present(
            true,
            true,
            false,
            $termId,
            'zh',
            $form['group'],
            $form['values'],
            '验证失败',
            '/admin/taxonomies?taxonomy=brand',
        );

        self::assertSame('taxonomy-form', $view->template);
        self::assertSame('编辑条目', $view->title);
        self::assertSame('', $view->data['values']['name']);
        self::assertSame('English (en)', $view->data['sourceReference']['locale']);
        self::assertSame('Apple', $view->data['sourceReference']['name']);
        self::assertSame('验证失败', $view->data['error']);
        self::assertArrayHasKey('zh', $view->data['locales']);
    }
}
