<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\TaxonomyListPresenter;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class TaxonomyListPresenterTest extends TestCase
{
    public function testItBuildsTermListContextAndPaginationUrls(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en,zh');
        $presenter = new TaxonomyListPresenter(
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                sys_get_temp_dir() . '/jinstan-taxonomy-list-presenter',
                TestUrlGenerator::adminViews(),
            ),
            $settings,
            TestUrlGenerator::adminDestinations(),
        );

        $view = $presenter->terms('brand', [
            'group' => ['handle' => 'brand', 'label' => '品牌'],
            'search' => 'app',
            'locale' => 'en',
            'result' => [
                'items' => [[
                    'id' => 1,
                    'name' => null,
                    'slug' => '',
                    'translated' => false,
                    'sourceLocale' => null,
                    'content_count' => 0,
                ]],
                'total' => 41,
                'page' => 2,
                'pages' => 3,
            ],
        ]);

        self::assertSame('taxonomy-terms', $view->template);
        self::assertSame('品牌', $view->title);
        self::assertSame('(未翻译)', $view->data['result']['items'][0]['name']);
        self::assertSame('locale=en&q=app&page=2', $view->data['context']);
        self::assertSame(
            '/admin/taxonomies?taxonomy=brand&locale=en&q=app&page=1',
            $view->data['previousUrl'],
        );
        self::assertSame(
            '/admin/taxonomies?taxonomy=brand&locale=en&q=app&page=3',
            $view->data['nextUrl'],
        );
    }
}
