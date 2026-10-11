<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Auth;

use DateTimeImmutable;
use Jinstan\Core\Auth\UserRepositoryContract;
use Jinstan\Core\Infrastructure\Database\Connection;

final class UserRepository implements UserRepositoryContract
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function all(): array
    {
        $users = $this->connection->fetchAllAssociative('SELECT id, email, created_at FROM users ORDER BY id');
        foreach ($users as &$user) {
            $user['roles'] = $this->roles((int) $user['id']);
            $user['role_labels'] = implode(', ', array_column($user['roles'], 'label'));
        }

        return $users;
    }

    public function find(int $id): array|false
    {
        $user = $this->connection->fetchAssociative('SELECT id, email, password_hash FROM users WHERE id = ?', [$id]);
        if ($user !== false) {
            $user['roles'] = array_column($this->roles($id), 'role_key');
        }

        return $user;
    }

    public function create(string $email, string $password, array $roles = []): int
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->beginTransaction();

        try {
            $this->connection->insert('users', [
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->replaceRoles($id, $roles);
            $this->connection->commit();

            return $id;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function updateProfile(int $id, string $email, ?string $password): void
    {
        $data = [
            'email' => $email,
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        if ($password !== null) {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->connection->update('users', $data, ['id' => $id]);
    }

    public function delete(int $id): void
    {
        if ($id === 1) {
            throw new \InvalidArgumentException('初始管理员不可删除。');
        }
        if ($this->hasRole($id, 'administrator') && $this->administratorCount() <= 1) {
            throw new \InvalidArgumentException('系统必须至少保留一名管理员。');
        }

        $this->connection->delete('users', ['id' => $id]);
    }

    public function updateRoles(int $id, array $roles): void
    {
        if ($this->hasRole($id, 'administrator') && !in_array('administrator', $roles, true) && $this->administratorCount() <= 1) {
            throw new \InvalidArgumentException('系统必须至少保留一名管理员。');
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->delete('user_roles', ['user_id' => $id]);
            $this->replaceRoles($id, $roles);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function roles(int $id): array
    {
        return $this->connection->fetchAllAssociative('SELECT roles.role_key, roles.label FROM user_roles INNER JOIN roles ON roles.role_key = user_roles.role_key WHERE user_roles.user_id = ? ORDER BY roles.role_key', [$id]);
    }

    private function replaceRoles(int $id, array $roles): void
    {
        foreach (array_values(array_unique($roles)) as $role) {
            if ($this->connection->fetchOne('SELECT role_key FROM roles WHERE role_key = ?', [$role]) === false) {
                throw new \InvalidArgumentException('用户包含未知角色。');
            }
            $this->connection->insert('user_roles', ['user_id' => $id, 'role_key' => $role]);
        }
    }

    private function hasRole(int $id, string $role): bool
    {
        return $this->connection->fetchOne('SELECT role_key FROM user_roles WHERE user_id = ? AND role_key = ?', [$id, $role]) !== false;
    }

    private function administratorCount(): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(DISTINCT user_id) FROM user_roles WHERE role_key = 'administrator'");
    }
}
