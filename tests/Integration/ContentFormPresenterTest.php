<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\View\Presenters\Admin\ContentFormPresenter;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Core\Infrastructure\View\TemplateCatalog;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class ContentFormPresenterTest extends TestCase
{
    public function testItPreparesLocaleFallbackParentAndSubmittedTaxonomy(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $contents = new ContentRepository($connection);
        $taxonomy = new TaxonomyRepository($connection);
        $taxonomy->createTaxonomy('topic', '主题');
        $term = $taxonomy->createTerm('topic', 'en', 'News', 'news');
        $parent = $contents->create(new Content(0, 'page', null, 'draft', null, null, []), new ContentTranslation('en', 'Parent', 'parent', null, []));
        $child = $contents->create(new Content(0, 'page', $parent->id, 'draft', null, null, []), new ContentTranslation('en', 'Child', 'child', null, []));
        $urls = TestUrlGenerator::contentAdmin();
        $presenter = new ContentFormPresenter(
            new AdminContentForm($urls),
            new CsrfToken(),
            $contents,
            $settings,
            $urls,
            $taxonomy,
            new TemplateCatalog(dirname(__DIR__, 2) . '/themes/default'),
        );
        $type = new ContentTypeDefinition('page', 'Page', [], 'content-detail', '/{slug}', true, ['topic' => ['multiple' => false, 'required' => false]]);

        $data = $presenter->present($type, ['locale' => 'ko', 'parent_id' => $parent->id, 'terms' => ['topic' => [(string) $term]]], $child->id, true);

        self::assertSame('ko', $data['form']['locale']);
        self::assertSame('#' . $parent->id, $data['form']['parents'][$parent->id]);
        self::assertSame('/admin/content/page/parents?locale=ko', $data['form']['parentSearchUrl']);
        self::assertSame('en', $data['sourceReference']['locale']);
        self::assertSame('Child', $data['sourceReference']['title']);
        $taxonomyConfig = json_decode($data['form']['taxonomyGroups'][0]['config'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($term, $taxonomyConfig['items'][0]['id']);
    }
}
