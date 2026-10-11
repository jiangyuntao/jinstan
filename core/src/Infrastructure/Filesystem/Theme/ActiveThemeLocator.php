<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Theme;

final class ActiveThemeLocator
{
    public function __construct(private readonly string $themesPath)
    {
    }

    public function locate(string $handle): string
    {
        $themesRoot = realpath($this->themesPath);
        $themePath = $themesRoot === false ? false : realpath($themesRoot . '/' . $handle);

        // 无效配置只能回退到内置主题，不能借由主题标识跳出 themes 目录。
        if ($themesRoot === false
            || $themePath === false
            || !is_dir($themePath)
            || !str_starts_with($themePath, $themesRoot . DIRECTORY_SEPARATOR)) {
            return $this->themesPath . '/default';
        }

        return $themePath;
    }
}
