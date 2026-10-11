<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\RoleAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\RoleAdminQuery;
use Jinstan\Core\Application\Auth\Services\RoleWriter;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\SafeErrorMessage;
use Jinstan\Core\Http\Requests\Admin\RoleRequest;
use Jinstan\Core\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RoleAdminController
{
    public function __construct(
        private readonly RoleWriter $writer,
        private readonly RoleAdminQuery $query,
        private readonly RoleAdminPresenter $presenter,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly RoleRequest $roleRequest,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->present($this->presenter->index($this->query->roles()));
    }

    public function form(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $key = isset($params['key']) ? (string) $params['key'] : null;
        try {
            $role = $this->query->role($key);
        } catch (\OutOfBoundsException) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->present($this->presenter->form($role, $this->query->permissions()));
    }

    public function save(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $original = isset($params['key']) ? (string) $params['key'] : null;
        $result = $this->roleRequest->result($request);
        $role = $result->normalized();
        try {
            $validated = $result->validated();
            $this->writer->save($original, $validated);
        } catch (\Throwable $exception) {
            return $this->present(
                $this->presenter->form($role, $this->query->permissions(), SafeErrorMessage::from($exception, '保存角色失败，请稍后重试。')),
                422,
            );
        }

        return $this->responses->redirect($this->indexUrl());
    }

    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $this->writer->delete((string) $params['key']);
        } catch (\Throwable $exception) {
            return $this->responses->text(SafeErrorMessage::from($exception, '删除角色失败，请稍后重试。'), 422);
        }

        return $this->responses->redirect($this->indexUrl());
    }

    private function indexUrl(): string
    {
        return $this->urls->route('admin.roles.index');
    }

    private function present(AdminPageView $view, int $status = 200): ResponseInterface
    {
        return $this->responses->html($view->render($this->views), $status);
    }
}
