<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Installer;

use DateTimeImmutable;
use Jinstan\Core\Infrastructure\Database\Connection;

final class InstallationSeeder
{
    public function seed(Connection $connection, string $siteName, string $email, string $password): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $connection->beginTransaction();

        try {
            $connection->insert('users', [
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $connection->insert('user_roles', [
                'user_id' => (int) $connection->lastInsertId(),
                'role_key' => 'administrator',
            ]);
            $connection->insert('settings', ['setting_key' => 'site.name', 'setting_value' => $siteName]);
            $connection->insert('settings', ['setting_key' => 'site.theme', 'setting_value' => 'default']);
            $connection->insert('settings', ['setting_key' => 'site.default_locale', 'setting_value' => 'en']);
            $connection->insert('settings', ['setting_key' => 'site.enabled_locales', 'setting_value' => 'zh-CN,ko,en']);
            $connection->insert('settings', [
                'setting_key' => 'site.locales',
                'setting_value' => json_encode([
                    'zh-CN' => ['name' => '简体中文', 'direction' => 'ltr'],
                    'ko' => ['name' => '한국어', 'direction' => 'ltr'],
                    'en' => ['name' => 'English', 'direction' => 'ltr'],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            $connection->insert('settings', ['setting_key' => 'site.fallback_locale', 'setting_value' => 'zh-CN']);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
