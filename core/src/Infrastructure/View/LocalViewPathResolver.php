<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\View\ViewPathResolver;

final class LocalViewPathResolver implements ViewPathResolver
{
    public function directory(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException(sprintf('View 目录不存在：%s', $path));
        }

        return $resolved;
    }

    public function containedPath(string $root, string $template, string $name): string
    {
        $path = $root . '/' . ltrim($template, '/');
        $directory = realpath(dirname($path));
        if ($directory === false
            || ($directory !== $root && !str_starts_with($directory, $root . DIRECTORY_SEPARATOR))) {
            throw new \InvalidArgumentException(sprintf('View 路径超出命名空间：%s', $name));
        }

        return $path;
    }
}
