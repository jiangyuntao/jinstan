<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Http\Controllers\Admin\ContentTypeAdminController;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Application\Content\Services\ContentTypeDeletionService;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Core\Infrastructure\View\TemplateCatalog;
use Jinstan\Tests\Support\TestUrlGenerator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ContentTypeAdminTest extends TestCase
{
    public function testContentTypeDefinitionCanBeEditedWithVisualFormData(): void
    {
        $projectPath = sys_get_temp_dir() . '/jinstan-content-types-' . bin2hex(random_bytes(6));
        mkdir($projectPath, 0750, true);
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $projectPath . '/database.sqlite']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $connection->insert('users', [
            'email' => 'admin@example.test',
            'password_hash' => password_hash('secure-password', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('user_roles', ['user_id' => (int) $connection->lastInsertId(), 'role_key' => 'administrator']);

        $_SESSION = [];
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
        self::assertTrue($auth->attempt('admin@example.test', 'secure-password'));

        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $csrf = new CsrfToken();
        $settings = new SettingsRepository($connection);
        $fieldTypes = new \Jinstan\Core\Field\FieldTypeRegistry();
        \Jinstan\Core\Field\DefaultFieldTypes::register($fieldTypes);
        $editable = new EditableContentTypes($settings, $fieldTypes);
        $taxonomy = new TaxonomyRepository($connection, $settings);
        $templates = new TemplateCatalog(dirname(__DIR__, 2) . '/themes/default');
        $router = new Router();
        $controller = new ContentTypeAdminController(
            $types,
            $editable,
            $csrf,
            new AdminViewRenderer(dirname(__DIR__, 2) . '/core/resources/views/admin', $projectPath . '/cache', TestUrlGenerator::adminViews()),
            TestUrlGenerator::adminDestinations(),
            new \Jinstan\Core\Http\ResponseFactory(),
            $taxonomy,
            $templates,
            $router,
            new ContentTypeDeletionService($types, $editable, new ContentRepository($connection), new ContentAttachmentStorage($projectPath . '/attachments')),
        );

        $edit = $controller->edit(new ServerRequest('GET', '/admin/content-types/new'), ['handle' => 'new']);
        self::assertSame(200, $edit->getStatusCode());
        self::assertStringContainsString('基础信息', (string) $edit->getBody());
        self::assertStringContainsString('添加字段', (string) $edit->getBody());
        self::assertStringContainsString('允许设置上级内容', (string) $edit->getBody());
        self::assertStringNotContainsString('name="definition"', (string) $edit->getBody());

        $articleEdit = $controller->edit(new ServerRequest('GET', '/admin/content-types/article'), ['handle' => 'article']);
        self::assertStringContainsString('内置字段由系统维护', (string) $articleEdit->getBody());
        self::assertStringNotContainsString('"name":"content"', (string) $articleEdit->getBody());

        $hiddenBuiltInFields = (new ServerRequest('POST', '/admin/content-types'))->withParsedBody([
            '_token' => $csrf->value(),
            'handle' => 'article',
            'label' => '文章',
            'defaultTemplate' => 'article-detail',
            'urlPattern' => '/blog/{slug}',
            'fields' => [],
        ]);
        self::assertSame(303, $controller->save($hiddenBuiltInFields, [])->getStatusCode());

        $modifiedBuiltInField = $hiddenBuiltInFields->withParsedBody([
            ...$hiddenBuiltInFields->getParsedBody(),
            'fields' => [['name' => 'content', 'label' => '篡改', 'type' => 'text']],
        ]);
        $protectedResponse = $controller->save($modifiedBuiltInField, []);
        self::assertSame(422, $protectedResponse->getStatusCode());
        self::assertStringContainsString('不能通过内容类型表单修改', (string) $protectedResponse->getBody());

        $save = (new ServerRequest('POST', '/admin/content-types'))->withParsedBody([
            '_token' => $csrf->value(),
            'handle' => 'review',
            'label' => '评测',
            'defaultTemplate' => 'content-detail',
            'urlPattern' => '/reviews/{slug}',
            'fields' => [
                ['name' => 'headline', 'label' => '标题', 'type' => 'text'],
                ['name' => 'sku', 'label' => 'SKU', 'type' => 'sku'],
                ['name' => 'summary', 'label' => '摘要', 'type' => 'textarea', 'required' => '1', 'translatable' => '1'],
                ['name' => 'body', 'label' => '正文', 'type' => 'body', 'allowHtmlDocument' => '1'],
                ['name' => 'quantity', 'label' => '数量', 'type' => 'number'],
                ['name' => 'score', 'label' => '评分', 'type' => 'range', 'rangeMin' => '1', 'rangeMax' => '5', 'rangeStep' => '0.5'],
                ['name' => 'featured', 'label' => '精选', 'type' => 'boolean'],
                ['name' => 'visible', 'label' => '显示', 'type' => 'switch'],
                ['name' => 'theme_color', 'label' => '主题颜色', 'type' => 'color'],
                ['name' => 'metadata', 'label' => '元数据', 'type' => 'json'],
                ['name' => 'published_on', 'label' => '发布日期', 'type' => 'date'],
                ['name' => 'published_at', 'label' => '发布时间', 'type' => 'datetime'],
                ['name' => 'start_time', 'label' => '开始时间', 'type' => 'time'],
                ['name' => 'month', 'label' => '月份', 'type' => 'month'],
                ['name' => 'week', 'label' => '周', 'type' => 'week'],
                ['name' => 'website', 'label' => '网站', 'type' => 'url'],
                ['name' => 'contact_email', 'label' => '邮箱', 'type' => 'email'],
                ['name' => 'phone', 'label' => '电话', 'type' => 'phone'],
                ['name' => 'category', 'label' => '分类', 'type' => 'select', 'optionsText' => "news | 新闻\nreview | 评测"],
                ['name' => 'author', 'label' => '作者', 'type' => 'searchable_select', 'optionsText' => 'theo | Theo'],
                ['name' => 'level', 'label' => '级别', 'type' => 'radio', 'optionsText' => "basic | 基础\npro | 专业"],
                ['name' => 'audience', 'label' => '受众', 'type' => 'checkbox_group', 'optionsText' => "developer | 开发者\neditor | 编辑"],
                ['name' => 'tags', 'label' => '标签', 'type' => 'tags'],
                ['name' => 'attachment', 'label' => '附件', 'type' => 'file'],
                ['name' => 'attachments', 'label' => '多个附件', 'type' => 'files'],
                ['name' => 'cover_image', 'label' => '封面图', 'type' => 'image'],
                ['name' => 'gallery', 'label' => '图库', 'type' => 'images'],
            ],
        ]);
        self::assertSame(303, $controller->save($save, [])->getStatusCode());

        $reloadedTypes = new ContentTypeRegistry();
        DefaultContentTypes::register($reloadedTypes);
        $editable->load($reloadedTypes);
        $saved = $reloadedTypes->get('review');
        self::assertSame('评测', $saved->label);
        self::assertTrue($saved->fields['summary']->required);
        self::assertTrue($saved->fields['summary']->translatable);
        self::assertTrue($saved->fields['body']->type->allowsHtmlDocument());
        self::assertSame([
            'text', 'sku', 'textarea', 'body', 'number', 'range', 'boolean', 'switch', 'color', 'json',
            'date', 'datetime', 'time', 'month', 'week', 'url', 'email', 'phone', 'select',
            'searchable_select', 'radio', 'checkbox_group', 'tags', 'file', 'files', 'image', 'images',
        ], array_column($editable->export($saved)['fields'], 'type'));

        $listController = new ContentTypeAdminController(
            $reloadedTypes,
            $editable,
            $csrf,
            new AdminViewRenderer(dirname(__DIR__, 2) . '/core/resources/views/admin', $projectPath . '/list-cache', TestUrlGenerator::adminViews()),
            TestUrlGenerator::adminDestinations(),
            new \Jinstan\Core\Http\ResponseFactory(),
            $taxonomy,
            $templates,
            $router,
            new ContentTypeDeletionService($reloadedTypes, $editable, new ContentRepository($connection), new ContentAttachmentStorage($projectPath . '/attachments')),
        );
        $listHtml = (string) $listController->index(new ServerRequest('GET', '/admin/content-types'), [])->getBody();
        self::assertStringContainsString('删除“评测”', $listHtml);
        self::assertStringContainsString('将同时删除该类型的全部内容、翻译和关联数据', $listHtml);
        self::assertStringNotContainsString('删除“文章”', $listHtml);
        self::assertSame(422, $listController->delete(
            (new ServerRequest('POST', '/admin/content-types/article/delete'))->withParsedBody(['_token' => $csrf->value()]),
            ['handle' => 'article'],
        )->getStatusCode());
        self::assertSame(303, $listController->delete(
            (new ServerRequest('POST', '/admin/content-types/review/delete'))->withParsedBody(['_token' => $csrf->value()]),
            ['handle' => 'review'],
        )->getStatusCode());
        self::assertStringNotContainsString('review', (string) (new SettingsRepository($connection))->get('content.types'));

        $missingOptions = (new ServerRequest('POST', '/admin/content-types'))->withParsedBody([
            '_token' => $csrf->value(),
            'handle' => 'invalid_options',
            'label' => '无选项',
            'fields' => [['name' => 'audience', 'label' => '受众', 'type' => 'checkbox_group']],
        ]);
        $invalidResponse = $controller->save($missingOptions, []);
        self::assertSame(422, $invalidResponse->getStatusCode());
        self::assertStringContainsString('选择类字段至少需要一个选项', (string) $invalidResponse->getBody());
    }
}
