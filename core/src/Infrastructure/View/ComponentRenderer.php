<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\Component\ComponentRegistry;
use Latte\Engine;

final class ComponentRenderer
{
    private readonly ComponentViewLocator $views;

    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly Engine $latte,
        string $themePath,
        ?string $pluginsPath,
        ?ComponentViewLocator $views = null,
    ) {
        $this->views = $views ?? new ComponentViewLocator($themePath, $pluginsPath);
    }

    /** @param array<string, mixed> $parameters
     *  @param array<string, mixed> $context
     */
    public function render(string $name, array $parameters, array $context): string
    {
        return $this->latte->renderToString(
            $this->views->locate($name),
            $this->components->get($name)->data($parameters, $context),
        );
    }
}
