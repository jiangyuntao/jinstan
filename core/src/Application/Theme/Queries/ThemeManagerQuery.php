<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Theme\Queries;

use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Application\Theme\ThemeFileGateway;

final class ThemeManagerQuery
{
    public function __construct(
        private readonly ThemeFileGateway $files,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function execute(array $parameters): array
    {
        $themes = $this->files->themes();
        $requestedTheme = $parameters['theme'] ?? null;
        $theme = is_string($requestedTheme) ? trim($requestedTheme) : (string) ($themes[0] ?? '');
        if (!in_array($theme, $themes, true)) {
            throw new \OutOfBoundsException('theme.not_found');
        }

        $availableFiles = $this->files->files($theme);
        $requestedFile = $parameters['file'] ?? null;
        $selected = is_string($requestedFile) ? trim($requestedFile) : '';
        if ($selected !== '' && !in_array($selected, $availableFiles, true)) {
            throw new \OutOfBoundsException('theme.file_not_found');
        }

        $activeTheme = $this->settings->get('site.theme', 'default') ?? 'default';

        return [
            'themes' => $themes,
            'theme' => $theme,
            'entries' => $this->files->entries($theme),
            'selected' => $selected,
            'source' => $selected === '' ? '' : $this->files->read($theme, $selected),
            'activeTheme' => $activeTheme,
            'canDeleteTheme' => $theme !== 'default' && $theme !== $activeTheme,
        ];
    }
}
