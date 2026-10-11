<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\SEO;

use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\SEO\RedirectRepositoryContract;

final class RedirectRepository implements RedirectRepositoryContract
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function add(string $source, string $target): void
    {
        if (!$this->isLocalPath($source, false)) {
            throw new \InvalidArgumentException('Redirect source must be a local path without a query or fragment.');
        }
        if (!$this->isLocalPath($target, true)) {
            throw new \InvalidArgumentException('Redirect target must be a local path.');
        }

        $this->connection->insert('redirects', [
            'source_path' => $source,
            'target_path' => $target,
            'status_code' => 301,
        ]);
    }

    public function target(string $source): ?string
    {
        $target = $this->connection->fetchOne(
            'SELECT target_path FROM redirects WHERE source_path = ? AND status_code = 301',
            [$source],
        );

        return is_string($target) && $this->isLocalPath($target, true) ? $target : null;
    }

    private function isLocalPath(string $path, bool $allowQueryAndFragment): bool
    {
        if ($path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return false;
        }

        $parts = parse_url($path);
        if (!is_array($parts)
            || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['port'])
            || ($parts['path'] ?? '') === '') {
            return false;
        }

        return $allowQueryAndFragment || (!isset($parts['query']) && !isset($parts['fragment']));
    }
}
