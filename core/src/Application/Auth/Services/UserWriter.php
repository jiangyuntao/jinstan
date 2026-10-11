<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Auth\Services;

use Jinstan\Core\Auth\RoleReadRepository;
use Jinstan\Core\Auth\UserRepositoryContract;

final class UserWriter
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly UserRepositoryContract $users,
        private readonly RoleReadRepository $roles,
    ) {
    }

    /** @param array<string, mixed> $validated */
    public function save(?int $id, array $validated): void
    {
        $roles = $validated['roles'];
        $availableRoles = array_column($this->roles->all(), 'role_key');
        if (array_diff($roles, $availableRoles) !== []) {
            throw new \InvalidArgumentException('用户包含未知角色。');
        }

        if ($id === null) {
            $this->users->create($validated['email'], $validated['password'], $roles);

            return;
        }

        $existingUser = $this->users->find($id);
        if ($existingUser === false) {
            throw new \OutOfBoundsException('用户不存在。');
        }
        if (
            $id === $this->auth->userId()
            && in_array('administrator', $existingUser['roles'], true)
            && !in_array('administrator', $roles, true)
        ) {
            throw new \InvalidArgumentException('不能移除自己当前的管理员角色。');
        }

        $password = $validated['password'];
        $this->users->updateProfile($id, $validated['email'], $password === '' ? null : $password);
        $this->users->updateRoles($id, $roles);
    }

    public function delete(int $id): void
    {
        $this->users->delete($id);
    }

    /** @param array<string, mixed> $validated */
    public function updateProfile(array $validated): void
    {
        $password = $validated['password'];
        $this->users->updateProfile(
            $this->auth->userId(),
            $validated['email'],
            $password === '' ? null : $password,
        );
    }
}
