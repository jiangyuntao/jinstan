<?php

declare(strict_types=1);

namespace Jinstan\Core\Auth;

/** Internal Extension API：角色与权限分配写入契约。 */
interface RoleWriteRepository
{
    /** @param list<string> $permissions */
    public function save(?string $originalKey, string $key, string $label, array $permissions): void;

    public function delete(string $key): void;
}
