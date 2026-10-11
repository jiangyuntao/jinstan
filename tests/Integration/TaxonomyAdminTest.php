<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Http\Controllers\Admin\TaxonomyAdminController;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyFormQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyIndexQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTermListQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTranslationResolver;
use Jinstan\Core\Application\Taxonomy\Services\ReorderTaxonomyTerms;
use Jinstan\Core\Application\Taxonomy\Services\SubmitTaxonomyForm;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Core\View\Presenters\Admin\TaxonomyFormPresenter;
use Jinstan\Core\View\Presenters\Admin\TaxonomyListPresenter;
use Jinstan\Tests\Support\TestUrlGenerator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class TaxonomyAdminTest extends TestCase
{
    private Connection $db;
    private TaxonomyRepository $repository;
    private TaxonomyAdminController $controller;
    private ContentTypeRegistry $types;
    private CsrfToken $csrf;

    protected function setUp(): void
    {
        $this->db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($this->db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $this->repository = new TaxonomyRepository($this->db);
        $this->repository->createTaxonomy('brand', '品牌');
        $this->types = new ContentTypeRegistry();
        $this->csrf = new CsrfToken();
        $this->db->insert('users', ['email' => 'taxonomy@example.test', 'password_hash' => 'unused', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $id = (int) $this->db->lastInsertId();
        $this->db->insert('user_roles', ['user_id' => $id, 'role_key' => 'administrator']);
        $_SESSION = ['_jinstan_user_id' => $id];
        $settings = new \Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository($this->db);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en,zh');
        $views = new AdminViewRenderer(
            dirname(__DIR__, 2) . '/core/resources/views/admin',
            sys_get_temp_dir() . '/jinstan-taxonomy-admin-cache',
            TestUrlGenerator::adminViews(),
        );
        $urls = TestUrlGenerator::adminDestinations();
        $translations = new TaxonomyTranslationResolver($this->repository, $settings);
        $this->controller = new TaxonomyAdminController(
            $this->csrf,
            $settings,
            new TaxonomyTermListQuery($this->repository, $settings, $translations),
            $translations,
            new ReorderTaxonomyTerms($this->repository),
            new SubmitTaxonomyForm($this->repository, $this->types),
            new TaxonomyFormQuery($this->repository),
            new TaxonomyFormPresenter($views, $settings, $translations),
            new TaxonomyIndexQuery($this->repository),
            new TaxonomyListPresenter($views, $settings, $urls),
            $views,
            $urls,
            new \Jinstan\Core\Http\ResponseFactory(),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testListsFormsValidationAndContext(): void
    {
        $response = $this->controller->index(new ServerRequest('GET', '/admin/taxonomies'), []);
        self::assertStringContainsString('管理条目', (string) $response->getBody());
        self::assertStringNotContainsString('taxonomySorter', (string) $response->getBody());
        $form = $this->controller->taxonomyForm(new ServerRequest('GET', '/admin/taxonomies/new'), []);
        self::assertStringNotContainsString(' readonly', (string) $form->getBody());
        $edit = $this->controller->taxonomyForm($this->post('/admin/taxonomies/brand/edit', ['label' => '制造商', 'handle' => 'changed']), ['handle' => 'brand']);
        self::assertSame(303, $edit->getStatusCode());
        self::assertSame('制造商', $this->repository->taxonomy('brand')['label']);
        $request = $this->post('/admin/taxonomies/brand/terms/new', ['name' => 'Apple', 'slug' => 'apple'])->withQueryParams(['q' => 'app', 'page' => 2]);
        $saved = $this->controller->termForm($request, ['handle' => 'brand']);
        self::assertSame('/admin/taxonomies?taxonomy=brand&locale=en&q=app&page=2', $saved->getHeaderLine('Location'));
        $id = (int) $this->repository->termPage('brand')['items'][0]['id'];
        $this->db->insert('term_translations', ['term_id' => $id, 'locale' => 'zh', 'name' => '苹果', 'slug' => 'pingguo']);
        $params = ['handle' => 'brand', 'id' => (string) $id];
        $edit = $this->controller->termForm($this->post('/edit', ['name' => '<Apple>', 'slug' => 'apple-new']), $params);
        self::assertSame(303, $edit->getStatusCode());
        self::assertSame('苹果', $this->db->fetchOne('SELECT name FROM term_translations WHERE term_id = ? AND locale = ?', [$id, 'zh']));
        $form = $this->controller->termForm(new ServerRequest('GET', '/edit'), $params);
        self::assertStringContainsString('&lt;Apple&gt;', (string) $form->getBody());
        $invalid = $this->controller->termForm($this->post('/edit', ['name' => 'Retained', 'slug' => '']), $params);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('Retained', (string) $invalid->getBody());
        self::assertSame('<Apple>', $this->repository->term('brand', $id)['name']);
        $duplicate = $this->controller->termForm($this->post('/new', ['name' => 'Duplicate', 'slug' => 'apple-new']), ['handle' => 'brand']);
        self::assertSame(422, $duplicate->getStatusCode());
        $this->repository->createTaxonomy('tag', '标签');
        self::assertSame(404, $this->controller->termForm(new ServerRequest('GET', '/edit'), ['handle' => 'tag', 'id' => (string) $id])->getStatusCode());
    }

    public function testDeletionConfirmationBindingsAndChildProtection(): void
    {
        $id = $this->repository->createTerm('brand', 'en', 'Parent', 'parent');
        $child = $this->repository->createTerm('brand', 'en', 'Child', 'child', $id);
        $params = ['handle' => 'brand', 'id' => (string) $id];
        $preview = $this->controller->termForm(new ServerRequest('GET', '/delete'), $params);
        self::assertStringContainsString('Parent', (string) $preview->getBody());
        self::assertSame(2, $this->repository->termPage('brand')['total']);
        self::assertSame(422, $this->controller->termForm($this->post('/delete', []), $params)->getStatusCode());
        self::assertSame(422, $this->controller->termForm($this->post('/delete', ['confirm' => 'yes']), $params)->getStatusCode());
        self::assertSame(422, $this->controller->taxonomyForm($this->post('/delete', ['confirm' => 'yes']), ['handle' => 'brand'])->getStatusCode());
        $this->repository->deleteTerm('brand', $child);
        self::assertSame(303, $this->controller->termForm($this->post('/delete', ['confirm' => 'yes']), $params)->getStatusCode());
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM term_translations'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM taxonomy_term_positions'));
        $this->types->register(new ContentTypeDefinition('product', '产品', [], 'content-detail', '/{slug}', false, ['brand' => ['multiple' => false, 'required' => false]]));
        $blocked = $this->controller->taxonomyForm($this->post('/delete', ['confirm' => 'yes']), ['handle' => 'brand']);
        self::assertSame(422, $blocked->getStatusCode());
        self::assertStringContainsString('产品', (string) $blocked->getBody());
        $this->types->register(new ContentTypeDefinition('product', '产品', [], 'content-detail', '/{slug}'));
        self::assertSame(303, $this->controller->taxonomyForm($this->post('/delete', ['confirm' => 'yes']), ['handle' => 'brand'])->getStatusCode());
        self::assertSame(
            [['handle' => 'category', 'label' => '分类', 'term_count' => 0]],
            $this->repository->taxonomies(),
        );
    }

    private function post(string $path, array $input): ServerRequest
    {
        return (new ServerRequest('POST', $path))->withParsedBody(['_token' => $this->csrf->value()] + $input);
    }
}
