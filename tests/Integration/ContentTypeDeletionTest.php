<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Application\Content\Services\ContentTypeDeletionService;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class ContentTypeDeletionTest extends TestCase
{
    public function testCustomTypeAndAllRelatedContentDataAreDeleted(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-content-type-delete-' . bin2hex(random_bytes(6));
        mkdir($path, 0750, true);
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $path . '/database.sqlite']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');

        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $wiki = new ContentTypeDefinition('wiki', '知识库', [], 'content-detail', '/wiki/{slug}');
        $types->register($wiki);
        $fieldTypes = new \Jinstan\Core\Field\FieldTypeRegistry();
        \Jinstan\Core\Field\DefaultFieldTypes::register($fieldTypes);
        $editable = new EditableContentTypes(new SettingsRepository($connection), $fieldTypes);
        $editable->save([$editable->export($wiki)]);
        $contents = new ContentRepository($connection);
        $article = $contents->create(new Content(0, 'article', null, 'draft', null, null, []), new ContentTranslation('zh-CN', '文章', 'article', null, []));
        $entry = $contents->create(new Content(0, 'wiki', null, 'draft', null, null, []), new ContentTranslation('zh-CN', '词条', 'entry', null, []));
        $contents->saveTranslation($entry->id, new ContentTranslation('en', 'Entry', 'entry-en', null, []));
        $contents->relate($article->id, 'reference', $entry->id);
        $connection->insert('taxonomies', ['handle' => 'topic', 'label' => '主题']);
        $connection->insert('terms', ['taxonomy_handle' => 'topic', 'parent_id' => null]);
        $connection->insert('content_terms', ['content_id' => $entry->id, 'term_id' => (int) $connection->lastInsertId()]);

        $service = new ContentTypeDeletionService($types, $editable, $contents, new ContentAttachmentStorage($path . '/attachments'));
        $service->delete('wiki');

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM contents WHERE type = ?', ['wiki']));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM content_translations WHERE content_id = ?', [$entry->id]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM content_relations WHERE target_content_id = ?', [$entry->id]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM content_terms WHERE content_id = ?', [$entry->id]));
        self::assertStringNotContainsString('wiki', (string) (new SettingsRepository($connection))->get('content.types'));
        self::assertNotNull($contents->find($article->id));
    }

    public function testBuiltInTypesCannotBeDeleted(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-content-type-protected-' . bin2hex(random_bytes(6));
        mkdir($path, 0750, true);
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $path . '/database.sqlite']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $fieldTypes = new \Jinstan\Core\Field\FieldTypeRegistry();
        \Jinstan\Core\Field\DefaultFieldTypes::register($fieldTypes);
        $editable = new EditableContentTypes(new SettingsRepository($connection), $fieldTypes);
        $service = new ContentTypeDeletionService($types, $editable, new ContentRepository($connection), new ContentAttachmentStorage($path . '/attachments'));

        $this->expectException(\InvalidArgumentException::class);
        $service->delete('article');
    }
}
