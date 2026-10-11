<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Theme\Services;

use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Application\Theme\ThemeFileGateway;

final class ThemeWriter
{
    public function __construct(
        private readonly ThemeFileGateway $files,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function saveFile(array $input): array
    {
        if ($this->text($input, 'confirm_overwrite') !== '1') {
            throw new \InvalidArgumentException('theme.overwrite_required');
        }
        $theme = $this->text($input, 'theme');
        $file = $this->text($input, 'file');
        $this->files->save($theme, $file, $this->rawText($input, 'source'));

        return ['theme' => $theme, 'file' => $file];
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function createTheme(array $input): array
    {
        $handle = $this->text($input, 'handle');
        $this->files->createTheme($handle, $this->text($input, 'name'));

        return ['theme' => $handle, 'file' => 'theme.json'];
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function deleteTheme(array $input): array
    {
        $theme = $this->text($input, 'theme');
        $this->confirm($input, $theme);
        $this->files->deleteTheme($theme, $this->settings->get('site.theme', 'default') ?? 'default');

        return ['theme' => '', 'file' => ''];
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function createDirectory(array $input): array
    {
        $theme = $this->text($input, 'theme');
        $this->files->createDirectory($theme, $this->text($input, 'parent'), $this->text($input, 'name'));

        return ['theme' => $theme, 'file' => ''];
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function deleteDirectory(array $input): array
    {
        $theme = $this->text($input, 'theme');
        $directory = $this->text($input, 'directory');
        $this->confirm($input, $directory);
        $this->files->deleteDirectory($theme, $directory);

        return ['theme' => $theme, 'file' => ''];
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function renameDirectory(array $input): array
    {
        $theme = $this->text($input, 'theme');
        $this->files->renameDirectory(
            $theme,
            $this->text($input, 'directory'),
            $this->text($input, 'name'),
        );

        return ['theme' => $theme, 'file' => ''];
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function createFile(array $input): array
    {
        $theme = $this->text($input, 'theme');
        $file = $this->files->createFile(
            $theme,
            $this->text($input, 'directory'),
            $this->text($input, 'name'),
        );

        return ['theme' => $theme, 'file' => $file];
    }

    /** @param array<string, mixed> $input @return array{theme: string, file: string} */
    public function deleteFile(array $input): array
    {
        $theme = $this->text($input, 'theme');
        $file = $this->text($input, 'file');
        $this->confirm($input, $file);
        $this->files->deleteFile($theme, $file);

        return ['theme' => $theme, 'file' => ''];
    }

    /** @param array<string, mixed> $input */
    private function confirm(array $input, string $target): void
    {
        if ($target === '' || $this->rawText($input, 'confirm') !== $target) {
            throw new \InvalidArgumentException('theme.delete_confirmation_invalid');
        }
    }

    /** @param array<string, mixed> $input */
    private function text(array $input, string $key): string
    {
        return trim($this->rawText($input, $key));
    }

    /** @param array<string, mixed> $input */
    private function rawText(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? $input[$key] : '';
    }
}
