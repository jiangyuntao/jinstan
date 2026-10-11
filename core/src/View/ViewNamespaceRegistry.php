<?php

declare(strict_types=1);

namespace Jinstan\Core\View;

final class ViewNamespaceRegistry implements ViewRegistrar
{
    /** @var array<string, string> */
    private array $paths = [];

    public function __construct(private readonly ViewPathResolver $resolver)
    {
    }

    public function register(string $namespace, string $path): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $namespace) !== 1) {
            throw new \InvalidArgumentException(sprintf('无效的 View Namespace：%s', $namespace));
        }

        $resolved = $this->resolver->directory($path);

        if (isset($this->paths[$namespace]) && $this->paths[$namespace] !== $resolved) {
            throw new \LogicException(sprintf('View Namespace "%s" 已注册。', $namespace));
        }

        $this->paths[$namespace] = $resolved;
    }

    public function resolve(string $name): string
    {
        [$namespace, $template] = $this->parse($name);
        $root = $this->paths[$namespace] ?? throw new \OutOfBoundsException(sprintf(
            'View Namespace "%s" 未注册。',
            $namespace,
        ));
        $template = str_replace('\\', '/', $template);
        if ($template === '' || str_starts_with($template, '/') || preg_match('#(^|/)\.\.(/|$)#', $template) === 1) {
            throw new \InvalidArgumentException(sprintf('无效的 View 路径：%s', $name));
        }

        return $this->resolver->containedPath($root, $template, $name);
    }

    /** @return array{string, string} */
    private function parse(string $name): array
    {
        $parts = explode('::', $name, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException(sprintf('View 名称必须包含命名空间：%s', $name));
        }

        return [$parts[0], $parts[1]];
    }
}
