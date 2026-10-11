<?php

declare(strict_types=1);

namespace Jinstan\Core\View;

interface ViewPathResolver
{
    public function directory(string $path): string;

    public function containedPath(string $root, string $template, string $name): string;
}
