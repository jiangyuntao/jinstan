<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Auth\Services;

use Jinstan\Core\Auth\RoleWriteRepository;

final class RoleWriter
{
    public function __construct(private readonly RoleWriteRepository $roles)
    {
    }

    /** @param array<string, mixed> $validated */
    public function save(?string $originalKey, array $validated): void
    {
        $this->roles->save(
            $originalKey,
            $validated['role_key'],
            $validated['label'],
            $validated['permissions'],
        );
    }

    public function delete(string $key): void
    {
        $this->roles->delete($key);
    }
}
