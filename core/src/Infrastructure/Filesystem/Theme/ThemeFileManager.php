<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Theme;

use Jinstan\Core\Application\Theme\ThemeFileGateway;
use Jinstan\Core\Application\Theme\ThemeSelectionGateway;
use Jinstan\Core\Infrastructure\View\TemplateCatalog;

final class ThemeFileManager implements ThemeFileGateway, ThemeSelectionGateway
{
    /** @var list<string> */
    private const EDITABLE_EXTENSIONS = ['css', 'js', 'json', 'jst', 'md', 'svg', 'txt'];

    /** @var list<string> */
    private const CREATABLE_EXTENSIONS = ['jst'];

    public function __construct(private readonly string $themesPath)
    {
    }

    /** @return list<string> */
    public function themes(): array
    {
        $themes = [];
        $root = $this->themesRoot();
        foreach (new \DirectoryIterator($root) as $theme) {
            $path = $theme->getRealPath();
            if ($theme->isDir() && !$theme->isDot() && is_string($path) && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                $themes[] = $theme->getFilename();
            }
        }
        sort($themes);

        return $themes;
    }

    /** @return array<string, string> */
    public function themeOptions(): array
    {
        $options = [];
        foreach ($this->themes() as $handle) {
            $manifestPath = $this->themesPath . '/' . $handle . '/theme.json';
            $manifest = is_file($manifestPath)
                ? json_decode((string) file_get_contents($manifestPath), true)
                : [];
            $options[$handle] = is_array($manifest) && is_string($manifest['name'] ?? null)
                ? $manifest['name']
                : $handle;
        }
        ksort($options);

        return $options;
    }

    public function options(): array
    {
        return $this->themeOptions();
    }

    public function hasHomepage(string $theme): bool
    {
        try {
            (new TemplateCatalog($this->themesPath . '/' . $theme))->homepage();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array{directory: bool, depth: int, name: string, path: string}> */
    public function entries(string $theme): array
    {
        $root = $this->themeRoot($theme);
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $path = $item->getRealPath();
            if (!is_string($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || $item->isLink()) {
                continue;
            }
            if (!$item->isDir() && (!$item->isFile() || !$this->editable($item->getFilename()))) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
            $entries[] = [
                'directory' => $item->isDir(),
                'depth' => substr_count($relative, '/'),
                'name' => $item->getFilename(),
                'path' => $relative,
            ];
        }
        usort($entries, static function (array $left, array $right): int {
            return strnatcasecmp($left['path'], $right['path']);
        });

        return $entries;
    }

    /** @return list<string> */
    public function files(string $theme): array
    {
        return array_values(array_map(
            static fn (array $entry): string => $entry['path'],
            array_filter($this->entries($theme), static fn (array $entry): bool => !$entry['directory']),
        ));
    }

    public function read(string $theme, string $file): string
    {
        $path = $this->existingPath($theme, $file, false);

        return (string) file_get_contents($path);
    }

    public function save(string $theme, string $file, string $source): void
    {
        $path = $this->existingPath($theme, $file, false);
        if (file_put_contents($path, $source, LOCK_EX) === false) {
            throw new \RuntimeException('theme.file_save_failed');
        }
    }

    public function createFile(string $theme, string $directory, string $name, string $source = ''): string
    {
        if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::CREATABLE_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('theme.file_type_invalid');
        }
        $relative = $this->join($directory, $name);
        $path = $this->newPath($theme, $relative);
        if (file_exists($path) || file_put_contents($path, $source, LOCK_EX) === false) {
            throw new \RuntimeException('theme.file_create_failed');
        }

        return $relative;
    }

    public function deleteFile(string $theme, string $file): void
    {
        $this->assertThemeFilesDeletable($theme);
        if ($file === 'theme.json') {
            throw new \RuntimeException('theme.manifest_delete_protected');
        }
        $path = $this->existingPath($theme, $file, false);
        if (!unlink($path)) {
            throw new \RuntimeException('theme.file_delete_failed');
        }
    }

    public function createDirectory(string $theme, string $parent, string $name): string
    {
        $relative = $this->join($parent, $name);
        $path = $this->newPath($theme, $relative);
        if (file_exists($path) || !mkdir($path, 0750)) {
            throw new \RuntimeException('theme.directory_create_failed');
        }

        return $relative;
    }

    public function deleteDirectory(string $theme, string $directory): void
    {
        $this->assertThemeFilesDeletable($theme);
        $path = $this->existingPath($theme, $directory, true);
        if ((new \FilesystemIterator($path))->valid()) {
            throw new \RuntimeException('theme.directory_not_empty');
        }
        if (!rmdir($path)) {
            throw new \RuntimeException('theme.directory_delete_failed');
        }
    }

    public function renameDirectory(string $theme, string $directory, string $name): string
    {
        if ($theme === 'default') {
            throw new \RuntimeException('theme.default_directories_rename_protected');
        }
        $path = $this->existingPath($theme, $directory, true);
        $this->assertSegment($name);
        $renamedPath = dirname($path) . '/' . $name;
        if (file_exists($renamedPath) || !rename($path, $renamedPath)) {
            throw new \RuntimeException('theme.directory_rename_failed');
        }

        $parent = dirname($directory);

        return $parent === '.' ? $name : $parent . '/' . $name;
    }

    public function createTheme(string $handle, string $name): void
    {
        $this->assertSegment($handle);
        $root = $this->themesRoot() . '/' . $handle;
        if (file_exists($root) || !mkdir($root, 0750)) {
            throw new \RuntimeException('theme.create_failed');
        }

        foreach (['layouts', 'templates', 'components', 'partials', 'assets'] as $directory) {
            mkdir($root . '/' . $directory, 0750);
        }
        $manifest = json_encode([
            'name' => trim($name) === '' ? $handle : trim($name),
            'version' => '1.0.0',
            'homepageTemplate' => 'homepage',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($root . '/theme.json', $manifest . PHP_EOL, LOCK_EX);
        file_put_contents($root . '/layouts/default.jst', "{block content}{/block}\n", LOCK_EX);
        file_put_contents($root . '/templates/homepage.jst', "{extends '../layouts/default.jst'}\n{block content}<main></main>{/block}\n", LOCK_EX);
        file_put_contents($root . '/templates/content-detail.jst', "{extends '../layouts/default.jst'}\n{block content}<main>{\$body_html}</main>{/block}\n", LOCK_EX);
        file_put_contents($root . '/templates/content-list.jst', "{extends '../layouts/default.jst'}\n{block content}<main>{foreach \$items as \$item}<a href=\"{\$item['url']}\">{\$item['title']}</a>{/foreach}</main>{/block}\n", LOCK_EX);
    }

    public function deleteTheme(string $theme, ?string $activeTheme): void
    {
        if ($theme === 'default' || $theme === $activeTheme) {
            throw new \RuntimeException('theme.delete_protected');
        }
        $root = $this->themeRoot($theme);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        if (!rmdir($root)) {
            throw new \RuntimeException('theme.delete_failed');
        }
    }

    private function existingPath(string $theme, string $relative, bool $directory): string
    {
        $this->assertRelativePath($relative);
        $root = $this->themeRoot($theme);
        $path = realpath($root . '/' . $relative);
        if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || is_link($path)) {
            throw new \InvalidArgumentException('theme.path_invalid');
        }
        if ($directory ? !is_dir($path) : !is_file($path) || !$this->editable($path)) {
            throw new \InvalidArgumentException('theme.path_invalid');
        }

        return $path;
    }

    private function newPath(string $theme, string $relative): string
    {
        $this->assertRelativePath($relative);
        $root = $this->themeRoot($theme);
        $parent = realpath(dirname($root . '/' . $relative));
        if ($parent === false || ($parent !== $root && !str_starts_with($parent, $root . DIRECTORY_SEPARATOR))) {
            throw new \InvalidArgumentException('theme.path_invalid');
        }

        return $parent . '/' . basename($relative);
    }

    private function join(string $directory, string $name): string
    {
        $this->assertSegment($name);
        if ($directory !== '') {
            $this->assertRelativePath($directory);
        }

        return $directory === '' ? $name : $directory . '/' . $name;
    }

    private function assertRelativePath(string $path): void
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('theme.path_invalid');
        }
        foreach (explode('/', $path) as $segment) {
            $this->assertSegment($segment);
        }
    }

    private function assertSegment(string $segment): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $segment) !== 1 || in_array($segment, ['.', '..'], true)) {
            throw new \InvalidArgumentException('theme.path_invalid');
        }
    }

    private function assertThemeFilesDeletable(string $theme): void
    {
        if ($theme === 'default') {
            throw new \RuntimeException('theme.default_files_protected');
        }
    }

    private function editable(string $file): bool
    {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EDITABLE_EXTENSIONS, true);
    }

    private function themeRoot(string $theme): string
    {
        if (!in_array($theme, $this->themes(), true)) {
            throw new \InvalidArgumentException('theme.not_found');
        }
        $root = realpath($this->themesRoot() . '/' . $theme);
        if ($root === false || !str_starts_with($root, $this->themesRoot() . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('theme.directory_unavailable');
        }

        return $root;
    }

    private function themesRoot(): string
    {
        $root = realpath($this->themesPath);
        if ($root === false) {
            throw new \RuntimeException('theme.directory_unavailable');
        }

        return $root;
    }
}
