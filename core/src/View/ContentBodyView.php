<?php

declare(strict_types=1);

namespace Jinstan\Core\View;

interface ContentBodyView
{
    public function render(mixed $value): string;
}
