<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Security\CsrfToken;

final class UserAdminPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly CsrfToken $csrf,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $users
     */
    public function index(array $users): AdminPageView
    {
        $users = array_map(static function (array $user): array {
            $user['deletable'] = (int) $user['id'] !== 1;

            return $user;
        }, $users);

        return new AdminPageView(
            'users',
            $this->views->translate('用户管理'),
            [
                'csrfToken' => $this->csrf->value(),
                'columns' => [
                    ['name' => 'id', 'label' => $this->views->translate('ID'), 'view' => 'metadata.jst'],
                    ['name' => 'email', 'label' => $this->views->translate('邮箱'), 'view' => 'text.jst'],
                    ['name' => 'role_labels', 'label' => $this->views->translate('角色'), 'view' => 'metadata.jst'],
                    ['name' => 'created_at', 'label' => $this->views->translate('创建时间'), 'view' => 'metadata.jst'],
                    [
                        'name' => 'actions',
                        'label' => $this->views->translate('操作'),
                        'view' => 'user-actions.jst',
                        'csrfToken' => $this->csrf->value(),
                    ],
                ],
                'users' => $users,
            ],
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $roles
     */
    public function form(array $user, array $roles, ?string $error = null): AdminPageView
    {
        $title = $user['id'] === null ? '新增用户' : '编辑用户';

        return new AdminPageView(
            'user-form',
            $this->views->translate($title),
            [
                'csrfToken' => $this->csrf->value(),
                'user' => $user,
                'roles' => $roles,
                'error' => $error,
            ],
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function profile(array $user, ?string $error = null): AdminPageView
    {
        return new AdminPageView(
            'profile',
            $this->views->translate('我的资料'),
            [
                'csrfToken' => $this->csrf->value(),
                'user' => $user,
                'error' => $error,
            ],
        );
    }
}
