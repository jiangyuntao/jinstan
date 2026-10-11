<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Theme;

interface ThemeSelectionGateway
{
    /** @return array<string, string> */
    public function options(): array;

    public function hasHomepage(string $theme): bool;
}
