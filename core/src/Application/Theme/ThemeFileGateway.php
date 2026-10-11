<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Theme;

interface ThemeFileGateway
{
    /** @return list<string> */
    public function themes(): array;

    /** @return list<array{directory: bool, depth: int, name: string, path: string}> */
    public function entries(string $theme): array;

    /** @return list<string> */
    public function files(string $theme): array;

    public function read(string $theme, string $file): string;

    public function save(string $theme, string $file, string $source): void;

    public function createTheme(string $handle, string $name): void;

    public function deleteTheme(string $theme, ?string $activeTheme): void;

    public function createDirectory(string $theme, string $parent, string $name): string;

    public function deleteDirectory(string $theme, string $directory): void;

    public function renameDirectory(string $theme, string $directory, string $name): string;

    public function createFile(string $theme, string $directory, string $name, string $source = ''): string;

    public function deleteFile(string $theme, string $file): void;
}
