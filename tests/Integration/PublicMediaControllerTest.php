<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Http\Controllers\Frontend\PublicMediaController;
use Jinstan\Core\Infrastructure\Filesystem\Content\PublicMediaFileReader;
use Jinstan\Core\Content\PublicMediaAccessPolicy;
use Jinstan\Core\Application\Content\Queries\PublishedMediaAccessPolicy;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class PublicMediaControllerTest extends TestCase
{
    public function testItServesStoredFilesWithImmutableSecurityHeaders(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-public-media-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $source = $directory . '/source.png';
        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZxZ8AAAAASUVORK5CYII=',
            true,
        ));
        $storage = new ContentAttachmentStorage($directory . '/files');
        $path = $storage->store($source, 'pixel.png', true);
        [$first, $second, $file] = explode('/', $path);

        $response = (new PublicMediaController(new PublicMediaFileReader($storage, $this->access(true)), new ResponseFactory()))->show(
            new ServerRequest('GET', '/media/' . $path),
            ['first' => $first, 'second' => $second, 'file' => $file],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame('public, max-age=300', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertNotSame('', (string) $response->getBody());
    }

    public function testItRejectsInvalidOrMissingStoredPaths(): void
    {
        $storage = new ContentAttachmentStorage(sys_get_temp_dir() . '/jinstan-public-media-empty-' . bin2hex(random_bytes(6)));
        $controller = new PublicMediaController(new PublicMediaFileReader($storage, $this->access(true)), new ResponseFactory());

        $invalid = $controller->show(
            new ServerRequest('GET', '/media/../../secret'),
            ['first' => '..', 'second' => '..', 'file' => 'secret'],
        );
        $missing = $controller->show(
            new ServerRequest('GET', '/media/aa/bb/missing.jpg'),
            ['first' => 'aa', 'second' => 'bb', 'file' => str_repeat('0', 32) . '.jpg'],
        );

        self::assertSame(404, $invalid->getStatusCode());
        self::assertSame(404, $missing->getStatusCode());
    }

    public function testItRejectsAStoredFileThatIsNotReferencedByPublishedContent(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-private-media-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $source = $directory . '/source.png';
        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZxZ8AAAAASUVORK5CYII=',
            true,
        ));
        $storage = new ContentAttachmentStorage($directory . '/files');
        $path = $storage->store($source, 'pixel.png', true);
        [$first, $second, $file] = explode('/', $path);
        $controller = new PublicMediaController(
            new PublicMediaFileReader($storage, $this->access(false)),
            new ResponseFactory(),
        );

        $response = $controller->show(
            new ServerRequest('GET', '/media/' . $path),
            ['first' => $first, 'second' => $second, 'file' => $file],
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPublishedMediaPolicyTracksCurrentPublicationState(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-media-policy-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $connection = (new ConnectionFactory())->create([
            'driver' => 'sqlite',
            'path' => $directory . '/database.sqlite',
        ]);
        (new MigrationRunner())->migrate(
            $connection,
            dirname(__DIR__, 2) . '/core/src/Database/Migrations',
        );
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $contents = new ContentRepository($connection);
        $path = 'aa/bb/' . str_repeat('1', 32) . '.png';
        $content = $contents->create(
            new Content(0, 'product', null, 'draft', null, null, ['cover_image' => $path]),
            new ContentTranslation('en', 'Draft', 'draft', null, []),
        );
        $policy = new PublishedMediaAccessPolicy($contents, $types);

        self::assertFalse($policy->allows($path));
        $contents->setPublished($content->id, true);
        self::assertTrue($policy->allows($path));
        $contents->setPublished($content->id, false);
        self::assertFalse($policy->allows($path));
    }

    private function access(bool $allowed): PublicMediaAccessPolicy
    {
        return new class ($allowed) implements PublicMediaAccessPolicy {
            public function __construct(private readonly bool $allowed)
            {
            }

            public function allows(string $path): bool
            {
                return $this->allowed;
            }
        };
    }
}
