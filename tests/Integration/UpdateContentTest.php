<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\Application\Content\Services\UpdateContent;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\FileField;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class UpdateContentTest extends TestCase
{
    public function testItReplacesOldAttachmentOnlyAfterSaveAndCleansFailedUpload(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-update-content-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $oldImage = $directory . '/old.png';
        file_put_contents($oldImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Jv6sAAAAASUVORK5CYII='));
        $newImage = $directory . '/new.png';
        file_put_contents($newImage, file_get_contents($oldImage) . 'different');

        try {
            $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
            (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
            $contents = new ContentRepository($connection);
            $types = new ContentTypeRegistry();
            $type = new ContentTypeDefinition('test', 'Test', ['file' => new FieldDefinition('file', 'File', new FileField())], 'content-detail', '/test/{slug}');
            $types->register($type);
            $storage = new ContentAttachmentStorage($directory . '/uploads');
            $oldPath = $storage->store($oldImage, 'old.png', false);
            $content = $contents->create(new Content(0, 'test', null, 'draft', null, null, ['file' => $oldPath]), new ContentTranslation('en', 'Old', 'old', null, []));
            $contents->create(new Content(0, 'test', null, 'draft', null, null, []), new ContentTranslation('en', 'Other', 'other', null, []));
            $action = new UpdateContent(new ContentService($types, $contents, new TaxonomyRepository($connection)), $contents, new ContentAttachmentCoordinator($storage, $contents, $types));
            $input = ['title' => 'Updated', 'slug' => 'updated', 'terms' => [], 'published' => '1'];
            $request = (new ServerRequest('POST', '/admin/content/test/' . $content->id))->withParsedBody($input)->withUploadedFiles(['data' => [
                'file' => $this->upload($newImage, 'new.png'),
            ]]);
            $action->execute($request->getUploadedFiles(), $type, $content->id, $input, 'en', null, false);

            $newPath = $contents->find($content->id)->data['file'];
            self::assertNotSame($oldPath, $newPath);
            self::assertFileDoesNotExist($directory . '/uploads/' . $oldPath);
            self::assertFileExists($directory . '/uploads/' . $newPath);
            self::assertSame('published', $contents->find($content->id)->status);

            $input['slug'] = 'other';
            $request = $request->withParsedBody($input)->withUploadedFiles(['data' => [
                'file' => $this->upload($oldImage, 'old.png'),
            ]]);
            try {
                $action->execute($request->getUploadedFiles(), $type, $content->id, $input, 'en', null, false);
                self::fail('Expected duplicate slug to be rejected.');
            } catch (\PDOException) {
                self::assertSame($newPath, $contents->find($content->id)->data['file']);
                self::assertFileExists($directory . '/uploads/' . $newPath);
                self::assertFileDoesNotExist($directory . '/uploads/' . $oldPath);
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

    private function upload(string $path, string $name): UploadedFile
    {
        return new UploadedFile(Stream::create(fopen($path, 'rb')), filesize($path), UPLOAD_ERR_OK, $name);
    }
}
