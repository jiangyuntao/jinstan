<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Auth\Services;

use Jinstan\Core\Auth\AuthenticationReadRepository;

class Authenticator
{
    private const SESSION_KEY = '_jinstan_user_id';
    private const DUMMY_PASSWORD_HASH = '$2y$10$JGRHJ2beFv5zP2Fgel3ihOyC5y1QLp0WpanOkktVjKWzJzylFZR8m';

    public function __construct(private readonly AuthenticationReadRepository $users)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->credentials($email);

        $passwordHash = $user['passwordHash'] ?? self::DUMMY_PASSWORD_HASH;
        if (!password_verify($password, $passwordHash) || $user === null) {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user['id'];

        return true;
    }

    public function userId(): ?int
    {
        $id = $_SESSION[self::SESSION_KEY] ?? null;

        return is_int($id) ? $id : null;
    }

    public function can(string $permission): bool
    {
        $userId = $this->userId();
        if ($userId === null) {
            return false;
        }

        // 内置管理员必须在新增 Core 或 Plugin 权限尚未同步时仍可完成升级与权限配置。
        if ($this->users->isAdministrator($userId)) {
            return true;
        }

        return $this->users->hasPermission($userId, $permission);
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
