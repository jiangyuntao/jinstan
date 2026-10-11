<?php

declare(strict_types=1);

namespace Jinstan\Core\View;

interface AdminView extends ViewRenderer
{
    /** @param array<string, string|int|float> $parameters */
    public function translate(string $key, array $parameters = []): string;
}
