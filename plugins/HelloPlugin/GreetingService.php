<?php

declare(strict_types=1);

namespace Jinstan\Plugins\HelloPlugin;

final readonly class GreetingService
{
    public function message(): string
    {
        return 'Hello from plugin.';
    }
}
