<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Event\ContentCreated;
use Jinstan\Core\Event\ContentPublished;
use Jinstan\Core\Event\ContentUnpublished;
use Jinstan\Core\Event\ContentUpdated;
use Jinstan\Core\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

final class ContentEventTest extends TestCase
{
    public function testContentServiceDispatchesCompletedObjectEvents(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $events = new EventDispatcher();
        $received = [];
        foreach ([ContentCreated::class, ContentUpdated::class, ContentPublished::class, ContentUnpublished::class] as $eventType) {
            $events->listen($eventType, static function (object $event) use (&$received): void {
                $received[] = $event;
            });
        }
        $service = new ContentService($types, new ContentRepository($connection), events: $events);

        $content = $service->create('page', new ContentTranslation('en', 'First', 'first', null, ['content' => 'Body']));
        $service->update($content->id, new ContentTranslation('en', 'Updated', 'updated', null, ['content' => 'New body']), []);
        $service->publish($content->id);
        $service->publish($content->id);
        $service->setPublished($content->id, false);

        self::assertCount(4, $received);
        self::assertInstanceOf(ContentCreated::class, $received[0]);
        self::assertSame('First', $received[0]->translation->title);
        self::assertInstanceOf(ContentUpdated::class, $received[1]);
        self::assertSame('Updated', $received[1]->translation->title);
        self::assertInstanceOf(ContentPublished::class, $received[2]);
        self::assertSame('published', $received[2]->content->status);
        self::assertInstanceOf(ContentUnpublished::class, $received[3]);
        self::assertSame('draft', $received[3]->content->status);
    }
}
