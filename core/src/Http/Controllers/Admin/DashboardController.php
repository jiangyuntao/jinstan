<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\DashboardPresenter;
use Jinstan\Core\Application\Dashboard\Queries\DashboardQuery;
use Jinstan\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardController
{
    public function __construct(
        private readonly DashboardQuery $query,
        private readonly DashboardPresenter $presenter,
        private readonly AdminViewRenderer $views,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $view = $this->presenter->present($this->query->execute());

        return $this->responses->html($view->render($this->views));
    }
}
