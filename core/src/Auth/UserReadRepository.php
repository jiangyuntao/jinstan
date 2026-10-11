<?php

declare(strict_types=1);

namespace Jinstan\Core\Auth;

/** Internal Extension API：后台用户查询契约。 */
interface UserReadRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array;

    /** @return array<string, mixed>|false */
    public function find(int $id): array|false;
}
