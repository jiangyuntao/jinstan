<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Plugin\Services;

use Jinstan\Core\Plugin\PluginRegistrar;
use Jinstan\Core\Plugin\PluginServiceProvider;
use Jinstan\Core\Plugin\PluginDiscoveryGateway;
use Jinstan\Core\Plugin\PluginMigrationGateway;
use Jinstan\Core\Plugin\PluginViewRegistrar;
use Jinstan\Core\Plugin\PluginStateRepositoryContract;
use RuntimeException;
use Jinstan\Core\Settings\SettingsRepositoryContract;
use Jinstan\Core\I18n\TranslationRegistry;

final class PluginManager
{
    private const UNINSTALLED_VERSION = '__uninstalled__';

    /** @var array<string, array{plugin: PluginServiceProvider, version: string, author: string, path: string, settings: list<array<string, mixed>>}> */
    private array $loaded = [];

    public function __construct(
        private readonly PluginStateRepositoryContract $states,
        private readonly PluginRegistrar $registrar,
        private readonly ?SettingsRepositoryContract $settings = null,
        private readonly ?PluginViewRegistrar $views = null,
        private readonly ?PluginMigrationGateway $migrations = null,
        private readonly ?TranslationRegistry $translations = null,
        private readonly ?PluginDiscoveryGateway $discovery = null,
    ) {
    }

    public function boot(string $pluginsPath): void
    {
        $discovery = $this->discovery ?? throw new RuntimeException('Plugin discovery 未配置。');
        foreach ($discovery->discover($pluginsPath) as $definition) {
            $this->load($definition['manifest_path'], $definition['manifest']);
        }
    }

    public function enable(string $name): void
    {
        $entry = $this->loaded[$name] ?? throw new RuntimeException(sprintf('插件未发现：%s。', $name));
        if ($this->states->version($name) === self::UNINSTALLED_VERSION) {
            $this->migrations?->migrate($name, $entry['path']);
            $entry['plugin']->install();
        }
        $entry['plugin']->enable();
        $this->states->save($name, $entry['version'], true);
        $this->register($entry['plugin']);
        $entry['plugin']->boot();
    }

    public function disable(string $name): void
    {
        $entry = $this->loaded[$name] ?? throw new RuntimeException(sprintf('插件未发现：%s。', $name));
        $entry['plugin']->disable();
        $this->states->save($name, $entry['version'], false);
    }

    public function uninstall(string $name): void
    {
        $entry = $this->loaded[$name] ?? throw new RuntimeException(sprintf('插件未发现：%s。', $name));
        if ($this->states->version($name) === self::UNINSTALLED_VERSION) {
            throw new RuntimeException(sprintf('插件已卸载：%s。', $name));
        }
        if ($this->states->enabled($name)) {
            $entry['plugin']->disable();
        }
        $entry['plugin']->uninstall();
        foreach ($entry['settings'] as $definition) {
            $this->settings?->delete($this->settingKey($name, (string) $definition['key']));
        }
        $this->states->save($name, self::UNINSTALLED_VERSION, false);
    }

    /** @return list<array{name: string, version: string, author: string, installed: bool, enabled: bool, settings: list<array<string, mixed>>}> */
    public function plugins(): array
    {
        $plugins = [];
        foreach ($this->loaded as $name => $entry) {
            $definitions = [];
            foreach ($entry['settings'] as $definition) {
                $definition['value'] = $this->settings?->get($this->settingKey($name, (string) $definition['key']), (string) ($definition['default'] ?? ''))
                    ?? (string) ($definition['default'] ?? '');
                $definitions[] = $definition;
            }
            $plugins[] = [
                'name' => $name,
                'version' => $entry['version'],
                'author' => $entry['author'],
                'installed' => $this->states->version($name) !== self::UNINSTALLED_VERSION,
                'enabled' => $this->states->enabled($name),
                'settings' => $definitions,
            ];
        }
        return $plugins;
    }

    /** @return array{name: string, version: string, author: string, installed: bool, enabled: bool, settings: list<array<string, mixed>>}|null */
    public function plugin(string $name): ?array
    {
        foreach ($this->plugins() as $plugin) {
            if ($plugin['name'] === $name) {
                return $plugin;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $values */
    public function saveSettings(string $name, array $values): void
    {
        if ($this->settings === null) {
            throw new RuntimeException('插件设置存储不可用。');
        }
        $entry = $this->loaded[$name] ?? throw new RuntimeException(sprintf('插件未发现：%s。', $name));
        if ($this->states->version($name) === self::UNINSTALLED_VERSION) {
            throw new RuntimeException(sprintf('插件已卸载：%s。', $name));
        }
        $validated = [];
        foreach ($entry['settings'] as $definition) {
            $key = (string) $definition['key'];
            $submitted = $values[$key] ?? '';
            if (!is_string($submitted)) {
                throw new RuntimeException(sprintf('%s格式无效。', (string) $definition['label']));
            }
            $value = trim($submitted);
            if (($definition['required'] ?? false) && $value === '') {
                throw new RuntimeException(sprintf('%s不能为空。', (string) $definition['label']));
            }
            if (($definition['type'] ?? 'text') === 'url' && $value !== '') {
                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                if (filter_var($value, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
                    throw new RuntimeException(sprintf('%s必须是有效的 HTTP 或 HTTPS URL。', (string) $definition['label']));
                }
            }
            if (($definition['type'] ?? 'text') === 'json' && $value !== '') {
                $decoded = json_decode($value);
                if (!$decoded instanceof \stdClass) {
                    throw new RuntimeException(sprintf('%s必须是 JSON 对象。', (string) $definition['label']));
                }
            }
            if (strlen($value) > 10000) {
                throw new RuntimeException(sprintf('%s内容过长。', (string) $definition['label']));
            }
            $validated[$key] = $value;
        }
        foreach ($validated as $key => $value) {
            $this->settings->set($this->settingKey($name, $key), $value);
        }
    }

    /** @param array<string, mixed> $manifest */
    private function load(string $manifestPath, array $manifest): void
    {
        $name = $manifest['name'] ?? null;
        $version = $manifest['version'] ?? null;
        $class = $manifest['class'] ?? null;
        if (!is_string($name) || !is_string($version) || !is_string($class)) {
            throw new RuntimeException(sprintf('插件 Manifest 无效：%s。', $manifestPath));
        }

        $discovery = $this->discovery ?? throw new RuntimeException('Plugin discovery 未配置。');
        $plugin = $discovery->loadEntry($manifestPath, $class, $name);
        $author = is_string($manifest['author'] ?? null) && trim($manifest['author']) !== ''
            ? trim($manifest['author'])
            : '未知作者';
        $settings = $this->settingDefinitions($manifest['settings'] ?? []);
        $pluginPath = dirname($manifestPath);
        $viewsPath = $discovery->optionalDirectory($pluginPath, 'views');
        if ($viewsPath !== null) {
            $this->views?->register($name, $viewsPath);
        }
        $translationsPath = $discovery->optionalDirectory($pluginPath, 'lang/admin');
        if ($translationsPath !== null) {
            $this->translations?->register($name, $translationsPath);
        }
        $this->loaded[$name] = [
            'plugin' => $plugin,
            'version' => $version,
            'author' => $author,
            'path' => $pluginPath,
            'settings' => $settings,
        ];

        $installed = $this->states->version($name);
        if ($installed === self::UNINSTALLED_VERSION) {
            return;
        }
        if ($installed === null) {
            $this->migrations?->migrate($name, dirname($manifestPath));
            $plugin->install();
            $plugin->enable();
            $this->states->save($name, $version, true);
        } elseif ($installed !== $version) {
            $this->migrations?->migrate($name, dirname($manifestPath));
            $plugin->upgrade($installed);
            $this->states->save($name, $version, $this->states->enabled($name));
        }
        if (!$this->states->enabled($name)) {
            return;
        }

        $this->register($plugin);
        $plugin->boot();
    }

    private function register(PluginServiceProvider $plugin): void
    {
        $plugin->register($this->registrar);
    }

    private function settingKey(string $plugin, string $key): string
    {
        return 'plugin.' . $plugin . '.' . $key;
    }

    /** @return list<array<string, mixed>> */
    private function settingDefinitions(mixed $definitions): array
    {
        if (!is_array($definitions)) {
            throw new RuntimeException('插件 settings 必须是数组。');
        }
        $result = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)
                || !preg_match('/^[a-z][a-z0-9_]*$/', (string) ($definition['key'] ?? ''))
                || !is_string($definition['label'] ?? null)
                || !in_array($definition['type'] ?? 'text', ['text', 'url', 'json'], true)) {
                throw new RuntimeException('插件设置定义无效。');
            }
            $result[] = $definition;
        }
        return $result;
    }
}
