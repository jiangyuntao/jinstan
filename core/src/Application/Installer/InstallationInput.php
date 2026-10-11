<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Installer;

final class InstallationInput
{
    public function __construct(private readonly string $projectPath)
    {
    }

    /** @param array<string, string> $input
     *  @return array{siteName: string, email: string, password: string, database: array<string, mixed>}
     */
    public function validate(array $input): array
    {
        $siteName = trim($input['site_name'] ?? '');
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $input['password'] ?? '';

        if ($siteName === '' || $email === false || mb_strlen($password) < 6) {
            throw new \InvalidArgumentException('请填写站点名称、有效邮箱和至少 6 位的密码。');
        }
        if (mb_strlen($password) > 1024) {
            throw new \InvalidArgumentException('密码长度不能超过 1024 个字符。');
        }

        return [
            'siteName' => $siteName,
            'email' => $email,
            'password' => $password,
            'database' => $this->database($input),
        ];
    }

    /** @param array<string, string> $input
     *  @return array<string, mixed>
     */
    private function database(array $input): array
    {
        $driver = $input['database_driver'] ?? 'sqlite';
        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'path' => $this->projectPath . '/storage/database/database.sqlite'];
        }
        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new \InvalidArgumentException('请选择支持的数据库驱动。');
        }

        return [
            'driver' => $driver,
            'host' => trim($input['database_host'] ?? ''),
            'port' => (int) ($input['database_port'] ?? 0),
            'database' => trim($input['database_name'] ?? ''),
            'username' => trim($input['database_username'] ?? ''),
            'password' => $input['database_password'] ?? '',
        ];
    }
}
