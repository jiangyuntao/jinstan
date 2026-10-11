<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\System\Services\CacheLogsMaintenance;
use Jinstan\Core\View\Presenters\Admin\CacheLogsPresenter;
use Jinstan\Core\Application\System\Queries\CacheLogsQuery;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CacheLogsController
{
    public function __construct(
        private readonly CacheLogsQuery $query,
        private readonly CacheLogsPresenter $presenter,
        private readonly CacheLogsMaintenance $maintenance,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function showCache(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->present($this->presenter->caches($this->query->caches()));
    }

    public function clearCache(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->maintenance->clearCache();

        return $this->responses->redirect($this->urls->route('admin.cache.index'));
    }

    public function showLogs(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->present($this->presenter->logs($this->query->logs()));
    }

    public function clearLogs(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->maintenance->clearLogs();

        return $this->responses->redirect($this->urls->route('admin.logs.index'));
    }

    private function present(AdminPageView $view): ResponseInterface
    {
        return $this->responses->html($view->render($this->views));
    }
}
