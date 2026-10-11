<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\Application\Content\TemplateCatalogReader;

/** Public API：读取 Theme 声明的模板，按列表／详情用途提供安全的选择。 */
final class TemplateCatalog implements TemplateCatalogReader
{
    /** @var array<string, array<string, string>> */
    private array $options = [];

    /** @var array<string, array<string, string>> */
    private array $files = [];

    /** @var array<string, string|null> */
    private array $paths = [];

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    /** @var list<string>|null */
    private ?array $handles = null;

    public function __construct(private readonly string $themePath)
    {
    }

    /** @return array<string, string> */
    public function options(string $kind): array
    {
        if (array_key_exists($kind, $this->options)) {
            return $this->options[$kind];
        }

        $manifest = $this->manifest();
        $definitions = is_array($manifest['templates'] ?? null) ? $manifest['templates'] : [];
        $homepage = is_string($manifest['homepageTemplate'] ?? null) ? $manifest['homepageTemplate'] : '';
        $options = [];
        foreach ($this->discoveredHandles() as $handle) {
            $definition = is_array($definitions[$handle] ?? null) ? $definitions[$handle] : [];
            $templateKind = is_string($definition['kind'] ?? null)
                ? $definition['kind']
                : $this->inferredKind($handle, $homepage);
            if ($templateKind === $kind) {
                $options[$handle] = (string) ($definition['label'] ?? $handle);
            }
        }

        return $this->options[$kind] = $options;
    }

    /** @return array<string, string> */
    public function files(string $kind): array
    {
        if (array_key_exists($kind, $this->files)) {
            return $this->files[$kind];
        }

        $files = [];
        foreach (array_keys($this->options($kind)) as $handle) {
            $path = $this->path($handle);
            if ($path !== null) {
                $files[$handle] = 'templates/' . basename($path);
            }
        }

        return $this->files[$kind] = $files;
    }

    public function path(?string $handle): ?string
    {
        if ($handle === null || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,99}$/D', $handle)) {
            return null;
        }
        if (array_key_exists($handle, $this->paths)) {
            return $this->paths[$handle];
        }
        $root = realpath($this->themePath . '/templates');
        $path = realpath($this->themePath . '/templates/' . $handle . '.jst');

        return $this->paths[$handle] = $root !== false
            && $path !== false
            && is_file($path)
            && str_starts_with($path, $root . DIRECTORY_SEPARATOR)
                ? $path
                : null;
    }

    public function validate(string $handle, string $kind): void
    {
        if (!isset($this->options($kind)[$handle])) {
            throw new \InvalidArgumentException('请选择当前主题提供的' . ($kind === 'list' ? '列表' : '详情') . '模板。');
        }
    }

    public function homepage(): string
    {
        $manifest = $this->manifest();
        $handle = is_string($manifest['homepageTemplate'] ?? null)
            ? $manifest['homepageTemplate']
            : '';

        if ($handle === '' || !isset($this->options('homepage')[$handle])) {
            throw new \RuntimeException('当前主题没有声明可用的首页模板。');
        }

        return $handle;
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }
        if (!is_file($this->themePath . '/theme.json')) {
            return $this->manifest = [];
        }

        $manifest = json_decode((string) file_get_contents($this->themePath . '/theme.json'), true);

        return $this->manifest = is_array($manifest) ? $manifest : [];
    }

    /** @return list<string> */
    private function discoveredHandles(): array
    {
        if ($this->handles !== null) {
            return $this->handles;
        }
        $handles = [];
        foreach (glob($this->themePath . '/templates/*.jst') ?: [] as $path) {
            $handle = pathinfo($path, PATHINFO_FILENAME);
            if ($this->path($handle) !== null) {
                $handles[] = $handle;
            }
        }
        sort($handles);

        return $this->handles = $handles;
    }

    private function inferredKind(string $handle, string $homepage): string
    {
        if ($handle === $homepage) {
            return 'homepage';
        }

        return str_ends_with($handle, '-list') ? 'list' : 'detail';
    }
}
