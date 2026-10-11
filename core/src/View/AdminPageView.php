<?php

declare(strict_types=1);

namespace Jinstan\Core\View;

use Jinstan\Core\View\AdminView as AdminViewRenderer;

final readonly class AdminPageView
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $template,
        public string $title,
        public array $data = [],
    ) {
        $valid = str_contains($template, '::')
            ? preg_match('#^[a-z][a-z0-9-]*::[a-z0-9][a-z0-9/_-]*[.]jst$#', $template) === 1
            : preg_match('#^[a-z0-9][a-z0-9/_-]*[a-z0-9]$#', $template) === 1;
        if (!$valid || str_contains($template, '..')) {
            throw new \InvalidArgumentException(sprintf('无效的后台 View 名称：%s', $this->template));
        }
    }

    public function render(AdminViewRenderer $renderer): string
    {
        return $renderer->render($this->template, $this->title, $this->data);
    }
}
