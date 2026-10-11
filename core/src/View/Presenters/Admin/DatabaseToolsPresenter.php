<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Security\CsrfToken;

final class DatabaseToolsPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly CsrfToken $csrf,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function present(array $data): AdminPageView
    {
        $integrity = $data['integrity'];
        $data['integrity'] = $integrity['translation'] === null
            ? $integrity['value']
            : $this->views->translate($integrity['translation']);
        $data['csrfToken'] = $this->csrf->value();

        return new AdminPageView('database-tools', $this->views->translate('数据库'), $data);
    }
}
