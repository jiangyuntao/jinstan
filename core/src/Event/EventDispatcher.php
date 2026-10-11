<?php

declare(strict_types=1);

namespace Jinstan\Core\Event;

final class EventDispatcher implements EventListenerRegistrar
{
    /** @var array<class-string, list<\Closure(object): void>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        if (!class_exists($event) && !interface_exists($event)) {
            throw new \InvalidArgumentException(sprintf('事件类不存在：%s。', $event));
        }

        $this->listeners[$event][] = \Closure::fromCallable($listener);
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listeners as $eventType => $listeners) {
            if (!$event instanceof $eventType) {
                continue;
            }
            foreach ($listeners as $listener) {
                $listener($event);
            }
        }

        return $event;
    }
}
