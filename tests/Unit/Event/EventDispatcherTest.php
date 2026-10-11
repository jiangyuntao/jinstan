<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Event;

use Jinstan\Core\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    public function testItDispatchesToConcreteAndInterfaceListenersInRegistrationOrder(): void
    {
        $events = new EventDispatcher();
        $received = [];
        $events->listen(ExampleEvent::class, static function (ExampleEvent $event) use (&$received): void {
            $received[] = 'concrete:' . $event->value;
        });
        $events->listen(ExampleEventContract::class, static function (ExampleEventContract $event) use (&$received): void {
            $received[] = 'contract:' . $event->value();
        });

        $event = new ExampleEvent('saved');

        self::assertSame($event, $events->dispatch($event));
        self::assertSame(['concrete:saved', 'contract:saved'], $received);
    }

    public function testItRejectsUnknownEventTypes(): void
    {
        $events = new EventDispatcher();

        $this->expectException(\InvalidArgumentException::class);
        $events->listen('Unknown\\Plugin\\Event', static function (): void {
        });
    }
}

interface ExampleEventContract
{
    public function value(): string;
}

final readonly class ExampleEvent implements ExampleEventContract
{
    public function __construct(public string $value)
    {
    }

    public function value(): string
    {
        return $this->value;
    }
}
