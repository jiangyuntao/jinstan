<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\View\ViewNamespaceRegistry;
use Latte\Loader;
use Latte\RuntimeException;
use Latte\TemplateNotFoundException;

final class NamespacedViewLoader implements Loader
{
    public function __construct(private readonly ViewNamespaceRegistry $views)
    {
    }

    public function getContent(string $name): string
    {
        $path = $this->views->resolve($name);
        if (!is_file($path)) {
            throw new TemplateNotFoundException(sprintf('Missing template file "%s".', $name));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Unable to read template file "%s".', $name));
        }

        return $content;
    }

    public function getReferredName(string $name, string $referringName): string
    {
        if (str_contains($name, '::')) {
            return $name;
        }

        [$namespace, $template] = explode('::', $referringName, 2);
        $directory = dirname($template);
        $relative = $directory === '.' ? $name : $directory . '/' . $name;

        return $namespace . '::' . $this->normalize($relative);
    }

    public function getUniqueId(string $name): string
    {
        return $this->views->resolve($name);
    }

    private function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \InvalidArgumentException(sprintf('View 引用超出命名空间：%s', $path));
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
