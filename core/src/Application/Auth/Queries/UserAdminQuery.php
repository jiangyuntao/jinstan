<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Auth\Queries;

use Jinstan\Core\Auth\RoleReadRepository;
use Jinstan\Core\Auth\UserReadRepository;

final class UserAdminQuery
{
    public function __construct(
        private readonly UserReadRepository $users,
        private readonly RoleReadRepository $roles,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function users(): array
    {
        return $this->users->all();
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return $this->roles->all();
    }

    /** @return array<string, mixed> */
    public function user(?int $id): array
    {
        if ($id === null) {
            return ['id' => null, 'email' => ''];
        }

        $user = $this->users->find($id);
        if ($user === false) {
            throw new \OutOfBoundsException('用户不存在。');
        }

        return $user;
    }
}
