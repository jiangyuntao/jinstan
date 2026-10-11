<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\Application\Content\Services\DeleteContent;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\FileField;
use Jinstan\Core\Event\ContentDeleted;
use Jinstan\Core\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

final class DeleteContentTest extends TestCase
{
    public function testItRollsBackDatabaseDeletionAndKeepsAttachmentOnFailure(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-delete-content-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $image = $directory . '/pixel.png';
        file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Jv6sAAAAASUVORK5CYII='));

        try {
            $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
            (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
            $contents = new ContentRepository($connection);
            $types = new ContentTypeRegistry();
            $types->register(new ContentTypeDefinition('test', 'Test', ['file' => new FieldDefinition('file', 'File', new FileField())], 'content-detail', '/test/{slug}'));
            $storage = new ContentAttachmentStorage($directory . '/uploads');
            $path = $storage->store($image, 'pixel.png', false);
            $content = $contents->create(new Content(0, 'test', null, 'draft', null, null, ['file' => $path]), new ContentTranslation('en', 'Example', 'example', null, []));
            $contents->saveTranslation($content->id, new ContentTranslation('zh-CN', '示例', 'example-zh', null, []));
            $events = new EventDispatcher();
            $deleted = [];
            $events->listen(ContentDeleted::class, static function (ContentDeleted $event) use (&$deleted): void {
                $deleted[] = $event;
            });
            $delete = new DeleteContent($contents, $types, new ContentAttachmentCoordinator($storage, $contents, $types), $events);

            self::assertFalse($delete->execute('other', $content->id));
            $connection->executeStatement("CREATE TRIGGER prevent_content_delete BEFORE DELETE ON contents BEGIN SELECT RAISE(ABORT, 'blocked'); END");
            try {
                $delete->execute('test', $content->id);
                self::fail('Expected delete to fail.');
            } catch (\PDOException) {
                self::assertNotNull($contents->find($content->id));
                self::assertNotNull($contents->translation($content->id, 'en'));
                self::assertNotNull($contents->translation($content->id, 'zh-CN'));
                self::assertFileExists($directory . '/uploads/' . $path);
            }
            $connection->executeStatement('DROP TRIGGER prevent_content_delete');
            self::assertTrue($delete->execute('test', $content->id));
            self::assertNull($contents->find($content->id));
            self::assertFileDoesNotExist($directory . '/uploads/' . $path);
            self::assertCount(1, $deleted);
            self::assertSame($content->id, $deleted[0]->content->id);
        } finally {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }
}
