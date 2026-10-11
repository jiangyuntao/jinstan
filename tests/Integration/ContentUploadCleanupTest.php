<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Http\Controllers\Admin\ContentAdminController;
use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Field\ContentBodyField;
use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\FileField;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use Jinstan\Tests\Support\ContentAdminControllerFactory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class ContentUploadCleanupTest extends TestCase
{
    public function testItKeepsSharedHashAttachmentUntilNoContentReferencesIt(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-shared-attachment-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $image = $directory . '/pixel.png';
        file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Jv6sAAAAASUVORK5CYII='));
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $contents = new ContentRepository($connection);
        $types = new ContentTypeRegistry();
        $types->register(new ContentTypeDefinition('test', 'Test', ['first' => new FieldDefinition('first', 'First', new FileField())], 'content-detail', '/test/{slug}'));
        $storage = new ContentAttachmentStorage($directory . '/uploads');

        try {
            $path = $storage->store($image, 'pixel.png', false);
            $content = $contents->create(new Content(0, 'test', null, 'draft', null, null, ['first' => $path]), new ContentTranslation('en', 'Shared', 'shared', null, []));
            $coordinator = new ContentAttachmentCoordinator($storage, $contents, $types);
            $coordinator->deleteUnreferenced([$path]);
            self::assertFileExists($directory . '/uploads/' . $path);

            $contents->delete($content->id);
            $coordinator->deleteUnreferenced([$path]);
            self::assertFileDoesNotExist($directory . '/uploads/' . $path);
        } finally {
            unlink($image);
            rmdir($directory . '/uploads');
            rmdir($directory);
        }
    }

    public function testItCleansNewAttachmentOnHtmlPermissionDenialAndPartialUploadFailure(): void
    {
        $root = dirname(__DIR__, 2);
        $directory = sys_get_temp_dir() . '/jinstan-upload-cleanup-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $image = $directory . '/pixel.png';
        file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Jv6sAAAAASUVORK5CYII='));
        $invalid = $directory . '/invalid.txt';
        file_put_contents($invalid, 'not an image');

        try {
            $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
            (new MigrationRunner())->migrate($connection, $root . '/core/src/Database/Migrations');
            $settings = new SettingsRepository($connection);
            $contents = new ContentRepository($connection);
            $types = new ContentTypeRegistry();
            $types->register(new ContentTypeDefinition('test', 'Test', [
                'first' => new FieldDefinition('first', 'First', new FileField()),
                'second' => new FieldDefinition('second', 'Second', new FileField()),
                'body' => new FieldDefinition('body', 'Body', new ContentBodyField(true)),
            ], 'content-detail', '/test/{slug}'));
            $csrf = new CsrfToken();
            $urls = TestUrlGenerator::contentAdmin();
            $controller = ContentAdminControllerFactory::create(
                new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection)),
                $types,
                $contents,
                new ContentService($types, $contents),
                new ContentAttachmentStorage($directory . '/uploads'),
                $csrf,
                new AdminViewRenderer($root . '/core/resources/views/admin', $directory . '/cache', TestUrlGenerator::adminViews(), $csrf),
                new AdminContentForm($urls),
                $settings,
                $urls,
                new \Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository($connection, $settings),
                new \Jinstan\Core\Infrastructure\View\TemplateCatalog($root . '/themes/default'),
            );
            $body = ['title' => 'Title', 'slug' => 'title', 'data' => ['body' => ['format' => 'html_document', 'value' => '<html></html>']]];
            $request = (new ServerRequest('POST', '/admin/content/test'))->withParsedBody($body)->withUploadedFiles([
                'data' => ['first' => $this->upload($image, 'pixel.png')],
            ]);
            self::assertSame(403, $controller->store($request, ['type' => 'test'])->getStatusCode());
            self::assertSame([], glob($directory . '/uploads/*/*/*') ?: []);

            $existing = $contents->create(new Content(0, 'test', null, 'draft', null, null, []), new ContentTranslation('zh-CN', 'Old', 'old', null, []));
            self::assertSame(403, $controller->update($request, ['type' => 'test', 'id' => $existing->id])->getStatusCode());
            self::assertSame([], glob($directory . '/uploads/*/*/*') ?: []);

            $body['data']['body']['format'] = 'markdown';
            $request = $request->withParsedBody($body)->withUploadedFiles(['data' => [
                'first' => $this->upload($image, 'pixel.png'),
                'second' => $this->upload($invalid, 'invalid.txt'),
            ]]);
            self::assertSame(422, $controller->store($request, ['type' => 'test'])->getStatusCode());
            self::assertSame([], glob($directory . '/uploads/*/*/*') ?: []);
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
