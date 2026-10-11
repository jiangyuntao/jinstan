<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Auth;

use Jinstan\Core\Auth\AuthenticationReadRepository;
use Jinstan\Core\Infrastructure\Database\Connection;

final class DatabaseAuthenticationRepository implements AuthenticationReadRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function credentials(string $email): ?array
    {
        $user = $this->connection->fetchAssociative(
            'SELECT id, password_hash FROM users WHERE email = ?',
            [$email],
        );

        return $user === false ? null : [
            'id' => (int) $user['id'],
            'passwordHash' => (string) $user['password_hash'],
        ];
    }

    public function isAdministrator(int $userId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM user_roles WHERE user_id = ? AND role_key = ?',
            [$userId, 'administrator'],
        ) !== false;
    }

    public function hasPermission(int $userId, string $permission): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM user_roles INNER JOIN role_permissions ON user_roles.role_key = role_permissions.role_key WHERE user_roles.user_id = ? AND role_permissions.permission_key = ?',
            [$userId, $permission],
        ) !== false;
    }
}
