<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Security\CsrfToken;

final class PluginAdminPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly CsrfToken $csrf,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $plugins
     */
    public function index(array $plugins): AdminPageView
    {
        return new AdminPageView(
            'plugins',
            $this->views->translate('插件'),
            [
                'csrfToken' => $this->csrf->value(),
                'plugins' => $plugins,
            ],
        );
    }

    /**
     * @param array<string, mixed> $plugin
     */
    public function settings(array $plugin): AdminPageView
    {
        return new AdminPageView(
            'plugin-settings',
            $this->views->translate('插件设置'),
            [
                'csrfToken' => $this->csrf->value(),
                'plugin' => $plugin,
            ],
        );
    }
}
