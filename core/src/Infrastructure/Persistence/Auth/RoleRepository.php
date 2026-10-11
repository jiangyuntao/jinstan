<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Auth;

use Jinstan\Core\Auth\RoleRepositoryContract;
use Jinstan\Core\Infrastructure\Database\Connection;

final class RoleRepository implements RoleRepositoryContract
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function all(): array
    {
        $roles = $this->connection->fetchAllAssociative('SELECT role_key, label FROM roles ORDER BY role_key');
        foreach ($roles as &$role) {
            $role['user_count'] = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_roles WHERE role_key = ?', [$role['role_key']]);
            $role['permission_count'] = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM role_permissions WHERE role_key = ?', [$role['role_key']]);
        }

        return $roles;
    }

    public function find(string $key): array|false
    {
        $role = $this->connection->fetchAssociative('SELECT role_key, label FROM roles WHERE role_key = ?', [$key]);
        if ($role === false) {
            return false;
        }
        $role['permissions'] = array_column($this->connection->fetchAllAssociative('SELECT permission_key FROM role_permissions WHERE role_key = ? ORDER BY permission_key', [$key]), 'permission_key');

        return $role;
    }

    public function permissions(): array
    {
        return $this->connection->fetchAllAssociative("SELECT permission_key, label FROM permissions WHERE permission_key IN ('content.manage', 'settings.manage', 'system.manage', 'users.manage') ORDER BY permission_key");
    }

    public function save(?string $originalKey, string $key, string $label, array $permissions): void
    {
        if (preg_match('/^[a-z][a-z0-9._-]{1,99}$/', $key) !== 1 || trim($label) === '') {
            throw new \InvalidArgumentException('角色标识或名称无效。');
        }
        if ($originalKey !== null && $key !== $originalKey) {
            throw new \InvalidArgumentException('角色标识创建后不可修改。');
        }

        $available = array_column($this->permissions(), 'permission_key');
        $permissions = $key === 'administrator' ? $available : array_values(array_unique($permissions));
        if (array_diff($permissions, $available) !== []) {
            throw new \InvalidArgumentException('角色包含未知权限。');
        }

        $this->connection->beginTransaction();
        try {
            if ($originalKey === null) {
                $this->connection->insert('roles', ['role_key' => $key, 'label' => trim($label)]);
            } else {
                $this->connection->update('roles', ['label' => trim($label)], ['role_key' => $originalKey]);
            }
            $this->connection->delete('role_permissions', ['role_key' => $key]);
            foreach ($permissions as $permission) {
                $this->connection->insert('role_permissions', ['role_key' => $key, 'permission_key' => $permission]);
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(string $key): void
    {
        if ($key === 'administrator') {
            throw new \InvalidArgumentException('内置管理员角色不可删除。');
        }
        if ($this->find($key) === false) {
            throw new \InvalidArgumentException('角色不存在。');
        }

        $this->connection->delete('roles', ['role_key' => $key]);
    }
}
