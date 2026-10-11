<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\UserAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\UserAdminQuery;
use Jinstan\Core\Application\Auth\Services\UserWriter;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\SafeErrorMessage;
use Jinstan\Core\Http\Requests\Admin\UserRequest;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserAdminController
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly CsrfToken $csrf,
        private readonly UserWriter $writer,
        private readonly UserAdminQuery $query,
        private readonly UserAdminPresenter $presenter,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly UserRequest $userRequest,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->present($this->presenter->index($this->query->users()));
    }

    public function form(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id = isset($params['id']) ? (int) $params['id'] : null;
        try {
            $user = $this->query->user($id);
        } catch (\OutOfBoundsException) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->present($this->presenter->form($user, $this->query->roles()));
    }

    public function save(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id = isset($params['id']) ? (int) $params['id'] : null;
        $result = $id === null ? $this->userRequest->createResult($request) : $this->userRequest->result($request);
        $user = ['id' => $id] + $result->normalized();

        try {
            $validated = $result->validated();
            $this->writer->save($id, $validated);
        } catch (\OutOfBoundsException) {
            return $this->responses->text('Not Found', 404);
        } catch (\Throwable $exception) {
            return $this->present(
                $this->presenter->form($user, $this->query->roles(), SafeErrorMessage::from($exception, '保存用户失败，请稍后重试。')),
                422,
            );
        }

        return $this->responses->redirect($this->url('admin.users.index'));
    }

    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $this->writer->delete((int) $params['id']);
        } catch (\Throwable $exception) {
            return $this->responses->text(SafeErrorMessage::from($exception, '删除用户失败，请稍后重试。'), 422);
        }

        return $this->responses->redirect($this->url('admin.users.index'));
    }

    public function profile(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $user = $this->query->user($this->auth->userId());
        } catch (\OutOfBoundsException) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->present($this->presenter->profile($user));
    }

    public function saveProfile(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $result = $this->userRequest->profileResult($request);
        try {
            $validated = $result->validated();
            $this->writer->updateProfile($validated);
        } catch (\Throwable $exception) {
            return $this->present(
                $this->presenter->profile($result->normalized(), SafeErrorMessage::from($exception, '保存资料失败，请稍后重试。')),
                422,
            );
        }

        return $this->responses->redirect($this->url('admin.profile'));
    }

    public function logout(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        if (!is_array($input) || !$this->csrf->valid((string) ($input['_token'] ?? ''))) {
            return $this->responses->text('CSRF token is invalid.', 419);
        }

        $this->auth->logout();
        $this->csrf->regenerate();

        return $this->responses->redirect($this->url('login.show'));
    }

    private function url(string $name): string
    {
        return $this->urls->route($name);
    }

    private function present(AdminPageView $view, int $status = 200): ResponseInterface
    {
        return $this->responses->html($view->render($this->views), $status);
    }
}
