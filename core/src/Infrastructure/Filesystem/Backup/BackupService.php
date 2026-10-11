<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Backup;

use Jinstan\Core\Application\Database\BackupGateway;
use Jinstan\Core\Infrastructure\Database\Connection;

final class BackupService implements BackupGateway
{
    public function __construct(private readonly Connection $connection, private readonly string $projectPath)
    {
    }

    public function create(): string
    {
        if ($this->connection->driver() !== 'sqlite') {
            throw new \RuntimeException('当前仅支持 SQLite 备份。');
        }
        $id = gmdate('YmdHis');
        $path = $this->projectPath . '/storage/backups/' . $id;
        if (!mkdir($path, 0750, true) && !is_dir($path)) {
            throw new \RuntimeException('无法创建备份目录。');
        }
        $source = $this->projectPath . '/storage/database/database.sqlite';
        if (!is_file($source) || !copy($source, $path . '/database.sqlite')) {
            throw new \RuntimeException('无法备份数据库。');
        }
        @chmod($path . '/database.sqlite', 0600);
        $config = $this->projectPath . '/storage/config/database.php';
        if (is_file($config) && !copy($config, $path . '/database.php')) {
            throw new \RuntimeException('无法备份数据库配置。');
        }
        if (is_file($path . '/database.php')) {
            @chmod($path . '/database.php', 0600);
        }
        $manifestPath = $path . '/manifest.json';
        file_put_contents($manifestPath, json_encode(['created_at' => gmdate(DATE_ATOM),'driver' => 'sqlite','database_sha256' => hash_file('sha256', $path.'/database.sqlite')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        @chmod($manifestPath, 0600);
        return basename($path);
    }
    /** @return list<array{id: string, createdAt: string, hash: string}> */
    public function all(): array
    {
        $directory = $this->projectPath . '/storage/backups';
        $backups = [];
        foreach (glob($directory . '/*/manifest.json') ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }
            $backups[] = ['id' => basename(dirname($manifestPath)), 'createdAt' => (string) ($manifest['created_at'] ?? ''), 'hash' => (string) ($manifest['database_sha256'] ?? '')];
        }
        usort($backups, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));
        return $backups;
    }

    /** @return array{items: list<array{id: string, createdAt: string, hash: string}>, page: int, pages: int, total: int} */
    public function paginate(int $page, int $perPage = 20): array
    {
        $backups = $this->all();
        $total = count($backups);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'items' => array_slice($backups, ($page - 1) * $perPage, $perPage),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function delete(string $id): void
    {
        if (preg_match('/^[0-9]{14}$/', $id) !== 1) {
            throw new \InvalidArgumentException('无效的备份标识。');
        }

        $directory = $this->projectPath . '/storage/backups/' . $id;
        if (!is_dir($directory) || !is_file($directory . '/manifest.json')) {
            throw new \RuntimeException('备份不存在。');
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $item) {
            if (!$item->isFile() || !unlink($item->getPathname())) {
                throw new \RuntimeException('无法删除备份。');
            }
        }

        if (!rmdir($directory)) {
            throw new \RuntimeException('无法删除备份。');
        }
    }
}
