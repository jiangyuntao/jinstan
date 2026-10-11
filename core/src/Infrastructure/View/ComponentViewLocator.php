<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

final class ComponentViewLocator
{
    private readonly string $corePath;

    public function __construct(
        private readonly string $themePath,
        private readonly ?string $pluginsPath,
        ?string $corePath = null,
    ) {
        $this->corePath = $corePath ?? dirname(__DIR__, 3) . '/resources/views/components';
    }

    public function locate(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(sprintf('无效的组件名称：%s。', $name));
        }

        $themeView = $this->themePath . '/components/' . $name . '.jst';
        if (is_file($themeView)) {
            return $themeView;
        }

        $pluginViews = glob(($this->pluginsPath ?? '') . '/*/views/components/' . $name . '.jst') ?: [];
        sort($pluginViews, SORT_STRING);
        if ($pluginViews !== []) {
            return $pluginViews[0];
        }

        $coreView = $this->corePath . '/' . $name . '.jst';
        if (is_file($coreView)) {
            return $coreView;
        }

        throw new \RuntimeException(sprintf('组件 "%s" 缺少 Latte 视图。', $name));
    }
}
