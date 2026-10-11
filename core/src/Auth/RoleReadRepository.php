<?php

declare(strict_types=1);

namespace Jinstan\Core\Auth;

/** Internal Extension API：角色与权限查询契约。 */
interface RoleReadRepository
{
    /** @return list<array{role_key: string, label: string, user_count: int, permission_count: int}> */
    public function all(): array;

    /** @return array{role_key: string, label: string, permissions: list<string>}|false */
    public function find(string $key): array|false;

    /** @return list<array{permission_key: string, label: string}> */
    public function permissions(): array;
}
