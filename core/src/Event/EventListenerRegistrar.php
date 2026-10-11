<?php

declare(strict_types=1);

namespace Jinstan\Core\Event;

interface EventListenerRegistrar
{
    /** @param class-string $event */
    public function listen(string $event, callable $listener): void;
}
