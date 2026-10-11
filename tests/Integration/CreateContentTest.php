<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\Application\Content\Services\CreateContent;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\FileField;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class CreateContentTest extends TestCase
{
    public function testItCreatesContentAndCleansAttachmentWhenSaveFails(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-create-content-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $image = $directory . '/pixel.png';
        file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Jv6sAAAAASUVORK5CYII='));

        try {
            $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
            (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
            $settings = new SettingsRepository($connection);
            $contents = new ContentRepository($connection);
            $types = new ContentTypeRegistry();
            $type = new ContentTypeDefinition('test', 'Test', ['file' => new FieldDefinition('file', 'File', new FileField())], 'content-detail', '/test/{slug}');
            $types->register($type);
            $storage = new ContentAttachmentStorage($directory . '/uploads');
            $action = new CreateContent(new ContentService($types, $contents, new TaxonomyRepository($connection)), new ContentAttachmentCoordinator($storage, $contents, $types), $settings);
            $input = ['title' => 'Example', 'slug' => 'example', 'terms' => [], 'published' => '1'];
            $request = (new ServerRequest('POST', '/admin/content/test'))->withParsedBody($input);
            $action->execute($request->getUploadedFiles(), $type, $input, 'en', null, false);

            $created = $contents->list('test')[0];
            self::assertSame('published', $created->status);
            self::assertSame('Example', $contents->translation($created->id, 'en')->title);

            $input['title'] = 'Duplicate';
            $request = $request->withParsedBody($input)->withUploadedFiles(['data' => [
                'file' => new UploadedFile(Stream::create(fopen($image, 'rb')), filesize($image), UPLOAD_ERR_OK, 'pixel.png'),
            ]]);
            try {
                $action->execute($request->getUploadedFiles(), $type, $input, 'en', null, false);
                self::fail('Expected duplicate slug to be rejected.');
            } catch (\PDOException) {
                self::assertCount(1, $contents->list('test'));
                self::assertSame([], glob($directory . '/uploads/*/*/*') ?: []);
            }
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
