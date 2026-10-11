<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Installer;

use Jinstan\Core\Application\Installer\EnvironmentGateway;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\View\InstallerView;

final class InstallPresenter
{
    public function __construct(
        private readonly CsrfToken $csrf,
        private readonly EnvironmentGateway $environment,
        private readonly UrlGenerator $urls,
        private readonly InstallerView $views,
    ) {
    }

    public function form(?string $error = null): string
    {
        return $this->views->render('install.jst', [
            'error' => $error,
            'csrfToken' => $this->csrf->value(),
            'action' => $this->urls->route('install.store'),
            'checks' => $this->environment->checks(),
        ]);
    }
}
