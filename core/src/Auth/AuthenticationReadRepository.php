<?php

declare(strict_types=1);

namespace Jinstan\Core\Auth;

/** Internal API：Authenticator 使用的最小凭据与 Permission 查询契约。 */
interface AuthenticationReadRepository
{
    /** @return array{id: int, passwordHash: string}|null */
    public function credentials(string $email): ?array;

    public function isAdministrator(int $userId): bool;

    public function hasPermission(int $userId, string $permission): bool;
}
