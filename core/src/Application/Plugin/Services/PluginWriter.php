<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Plugin\Services;

final class PluginWriter
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    /** @param array<string, mixed> $input */
    public function toggle(string $name, array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            $enabled = filter_var($input['enabled'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($enabled === null) {
                throw new \InvalidArgumentException('插件状态无效。');
            }
        } else {
            $action = is_string($input['action'] ?? null) ? $input['action'] : '';
            if (!in_array($action, ['enable', 'disable'], true)) {
                throw new \InvalidArgumentException('插件操作无效。');
            }
            $enabled = $action === 'enable';
        }

        if ($enabled) {
            $this->plugins->enable($name);

            return;
        }

        $this->plugins->disable($name);
    }

    /** @param array<string, mixed> $input */
    public function saveSettings(string $name, array $input): void
    {
        $values = $input['settings'] ?? [];
        if (!is_array($values)) {
            throw new \InvalidArgumentException('插件设置格式无效。');
        }

        $this->plugins->saveSettings($name, $values);
    }

    public function uninstall(string $name): void
    {
        $this->plugins->uninstall($name);
    }
}
