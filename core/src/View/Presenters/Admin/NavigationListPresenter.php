<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;

final class NavigationListPresenter
{
    public function __construct(private readonly AdminViewRenderer $views)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function present(array $data): array
    {
        foreach ($data['items'] as &$item) {
            $item['label'] ??= $this->views->translate('content.untranslated');
        }
        unset($item);

        return $data;
    }
}
