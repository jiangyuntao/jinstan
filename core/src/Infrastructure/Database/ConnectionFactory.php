<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Database;

use InvalidArgumentException;
use PDO;

final class ConnectionFactory
{
    /** @param array<string, mixed> $config */
    public function create(array $config): Connection
    {
        $driver = $config['driver'] ?? null;

        if ($driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . ($config['path'] ?? throw new InvalidArgumentException('缺少 SQLite 数据库路径。')), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA foreign_keys = ON');

            return new Connection($pdo, 'sqlite');
        }

        if ($driver === 'mysql') {
            return $this->networkConnection($config, 'mysql');
        }

        if ($driver === 'pgsql') {
            return $this->networkConnection($config, 'pgsql');
        }

        throw new InvalidArgumentException('不支持的数据库驱动。');
    }

    /** @param array<string, mixed> $config
     *  @return array<string, mixed>
     */
    private function networkConnection(array $config, string $driver): Connection
    {
        foreach (['host', 'database', 'username'] as $key) {
            if (!is_string($config[$key] ?? null) || $config[$key] === '') {
                throw new InvalidArgumentException(sprintf('缺少数据库配置：%s。', $key));
            }
        }

        $port = $config['port'] ?? ($driver === 'mysql' ? 3306 : 5432);
        $dsn = $driver === 'mysql'
            ? sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $port, $config['database'])
            : sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $port, $config['database']);

        return new Connection(new PDO($dsn, $config['username'], $config['password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), $driver);
    }
}
