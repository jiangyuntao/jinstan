<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Database\Services\DatabaseMaintenance;
use Jinstan\Core\View\Presenters\Admin\DatabaseToolsPresenter;
use Jinstan\Core\Application\Database\Queries\DatabaseToolsQuery;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\SafeErrorMessage;
use Jinstan\Core\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DatabaseToolsController
{
    public function __construct(
        private readonly DatabaseToolsQuery $query,
        private readonly DatabaseToolsPresenter $presenter,
        private readonly DatabaseMaintenance $maintenance,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $view = $this->presenter->present($this->query->execute($request->getQueryParams()));

        return $this->responses->html($view->render($this->views));
    }

    public function check(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->query->integrity();

        return $this->responses->redirect($this->indexUrl());
    }

    public function optimize(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->maintenance->optimize();

        return $this->responses->redirect($this->indexUrl());
    }

    public function cleanup(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $removed = $this->maintenance->cleanup();

        return $this->responses->redirect($this->indexUrl() . '?cleaned=' . $removed);
    }

    public function backup(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $this->maintenance->createBackup();
        } catch (\Throwable $exception) {
            return $this->responses->text(SafeErrorMessage::from($exception, '创建备份失败，请稍后重试。'), 422);
        }

        return $this->responses->redirect($this->indexUrl(true));
    }

    public function deleteBackup(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $this->maintenance->deleteBackup((string) ($params['id'] ?? ''));
        } catch (\InvalidArgumentException $exception) {
            return $this->responses->text($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            return $this->responses->text(SafeErrorMessage::from($exception, '删除备份失败，请稍后重试。'), 422);
        }

        return $this->responses->redirect($this->indexUrl(true));
    }

    private function indexUrl(bool $backups = false): string
    {
        $url = $this->urls->route('admin.database.index');

        return $backups ? $url . '#backups' : $url;
    }

}
