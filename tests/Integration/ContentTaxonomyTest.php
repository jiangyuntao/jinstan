<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class ContentTaxonomyTest extends TestCase
{
    public function testBindingsSelectionsAndRollback(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-bindings-' . bin2hex(random_bytes(5));
        mkdir($path, 0750, true);
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $path . '/db.sqlite']);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $taxonomy = new TaxonomyRepository($db);
        $taxonomy->createTaxonomy('brand', '品牌');
        $taxonomy->createTaxonomy('tag', '标签');
        $brand = $taxonomy->createTerm('brand', 'en', 'Apple', 'apple');
        $tag = $taxonomy->createTerm('tag', 'en', 'Mobile', 'mobile');
        $types = new ContentTypeRegistry();
        $fieldTypes = new \Jinstan\Core\Field\FieldTypeRegistry();
        \Jinstan\Core\Field\DefaultFieldTypes::register($fieldTypes);
        $editable = new EditableContentTypes(new SettingsRepository($db), $fieldTypes);
        foreach (['article', 'page', 'product'] as $type) {
            $definition = new ContentTypeDefinition($type, $type, [], 'content-detail', '/{slug}', false, [
                'brand' => ['multiple' => false, 'required' => true],
                'tag' => ['multiple' => true, 'required' => false],
            ]);
            $types->register($editable->definition($editable->export($definition)));
        }
        $contents = new ContentRepository($db);
        $service = new ContentService($types, $contents, $taxonomy);
        foreach (['article', 'page', 'product'] as $type) {
            $translation = new ContentTranslation('en', $type, $type, null, []);
            $content = $service->saveWithTerms($type, null, $translation, [], ['brand' => [$brand], 'tag' => [$tag]], null, null, false);
            self::assertCount(2, $taxonomy->selected($content->id));
            $service->saveWithTerms($type, $content->id, $translation, [], ['brand' => [$brand], 'tag' => ['']], null, null, false);
            self::assertCount(1, $taxonomy->selected($content->id));
            foreach ([['brand' => []], ['brand' => [$tag]], ['unknown' => [$tag]]] as $invalid) {
                try {
                    $service->saveWithTerms($type, $content->id, new ContentTranslation('en', 'Bad', $type, null, []), [], $invalid, null, null, false);
                    self::fail('Invalid selection accepted');
                } catch (\InvalidArgumentException) {
                    self::assertSame($type, $contents->translation($content->id, 'en')->title);
                    self::assertCount(1, $taxonomy->selected($content->id));
                }
            }
        }
        try {
            $service->saveWithTerms('article', null, new ContentTranslation('en', 'Duplicate', 'article', null, []), [], ['brand' => [$brand]], null, null, false);
            self::fail('Duplicate slug accepted');
        } catch (\PDOException) {
            self::assertSame(3, (int) $db->fetchOne('SELECT COUNT(*) FROM contents'));
        }
        self::assertCount(0, $taxonomy->search('tag', 'Apple'));
        self::assertCount(1, $taxonomy->search('brand', 'Apple'));
        $renderer = new AdminViewRenderer(dirname(__DIR__, 2) . '/core/resources/views/admin', $path . '/cache', TestUrlGenerator::adminViews());
        $html = $renderer->render('content-form', '编辑', ['pageHeading' => '编辑', 'error' => null, 'sourceReference' => null, 'form' => [
            'typeHandle' => 'article', 'action' => '/', 'csrfToken' => '', 'title' => '', 'slug' => '', 'hierarchical' => false, 'fields' => [],
            'locale' => 'en', 'locales' => ['en' => 'English'],
            'published' => false, 'deleteAction' => null, 'taxonomyGroups' => [
                ['handle' => 'brand', 'label' => '品牌', 'required' => true, 'config' => json_encode(['items' => [], 'multiple' => false, 'url' => '/search'])],
            ],
        ]]);
        self::assertStringContainsString('name="terms[brand][]"', $html);
        self::assertStringContainsString('taxonomyPicker', $html);
        $definition = $editable->export($types->get('product'));
        $editable->save([$definition]);
        $reloaded = new ContentTypeRegistry();
        $editable->load($reloaded);
        self::assertSame($definition['taxonomies'], $reloaded->get('product')->taxonomies);
        $html = $renderer->render('content-types', '编辑', [
            'definition' => $definition, 'fieldsJson' => '[]', 'error' => null,
            'taxonomyGroups' => $taxonomy->taxonomies(), 'hasProtectedFields' => false,
        ]);
        self::assertStringContainsString('name="taxonomies[brand][enabled]" value="1" checked', $html);
        self::assertStringContainsString('name="taxonomies[brand][required]" value="1" checked', $html);
    }
}
