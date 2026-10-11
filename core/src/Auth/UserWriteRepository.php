<?php

declare(strict_types=1);

namespace Jinstan\Core\Auth;

/** Internal Extension API：用户与角色分配写入契约。 */
interface UserWriteRepository
{
    /** @param list<string> $roles */
    public function create(string $email, string $password, array $roles = []): int;

    public function updateProfile(int $id, string $email, ?string $password): void;

    /** @param list<string> $roles */
    public function updateRoles(int $id, array $roles): void;

    public function delete(int $id): void;
}
