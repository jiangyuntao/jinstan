<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Security\CsrfToken;

final class RoleAdminPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly CsrfToken $csrf,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $roles
     */
    public function index(array $roles): AdminPageView
    {
        return new AdminPageView(
            'roles',
            $this->views->translate('角色管理'),
            [
                'roles' => $roles,
                'csrfToken' => $this->csrf->value(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $role
     * @param list<array<string, mixed>> $permissions
     */
    public function form(array $role, array $permissions, ?string $error = null): AdminPageView
    {
        return new AdminPageView(
            'role-form',
            $this->views->translate($role['role_key'] === '' ? '新增角色' : '编辑角色'),
            [
                'role' => $role,
                'permissions' => $permissions,
                'csrfToken' => $this->csrf->value(),
                'error' => $error,
            ],
        );
    }
}
