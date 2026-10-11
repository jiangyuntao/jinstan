<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Auth\Queries;

use Jinstan\Core\Auth\RoleReadRepository;

final class RoleAdminQuery
{
    public function __construct(private readonly RoleReadRepository $roles)
    {
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return $this->roles->all();
    }

    /** @return list<array<string, mixed>> */
    public function permissions(): array
    {
        return $this->roles->permissions();
    }

    /** @return array<string, mixed> */
    public function role(?string $key): array
    {
        if ($key === null) {
            return ['role_key' => '', 'label' => '', 'permissions' => []];
        }

        $role = $this->roles->find($key);
        if ($role === false) {
            throw new \OutOfBoundsException('角色不存在。');
        }

        return $role;
    }
}
