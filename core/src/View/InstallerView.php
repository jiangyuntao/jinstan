<?php

declare(strict_types=1);

namespace Jinstan\Core\View;

interface InstallerView
{
    /** @param array<string, mixed> $data */
    public function render(string $view, array $data): string;
}
