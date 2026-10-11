<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Security;

use Jinstan\Core\Security\RateLimiter;
use Jinstan\Core\Security\RateLimitResult;

final class FileRateLimiter implements RateLimiter
{
    public function __construct(private readonly string $directory)
    {
    }

    public function consume(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult
    {
        if ($key === '' || $maxAttempts < 1 || $decaySeconds < 1) {
            throw new \InvalidArgumentException('限速参数无效。');
        }

        $this->ensureDirectory();
        $path = $this->path($key);
        $handle = fopen($path, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException('无法打开限速状态文件。');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('无法锁定限速状态文件。');
            }
            @chmod($path, 0600);
            $now = time();
            $state = $this->read($handle);
            $startedAt = (int) ($state['started_at'] ?? $now);
            $attempts = (int) ($state['attempts'] ?? 0);
            if ($startedAt > $now || $startedAt + $decaySeconds <= $now) {
                $startedAt = $now;
                $attempts = 0;
            }

            $retryAfter = max(1, $startedAt + $decaySeconds - $now);
            if ($attempts >= $maxAttempts) {
                return new RateLimitResult(false, $retryAfter);
            }

            ++$attempts;
            $this->write($handle, $startedAt, $attempts);

            return new RateLimitResult(true, 0);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function clear(string $key): void
    {
        if ($key === '') {
            return;
        }
        $path = $this->path($key);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('无法清除限速状态。');
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)
            && !mkdir($this->directory, 0750, true)
            && !is_dir($this->directory)) {
            throw new \RuntimeException('无法创建限速状态目录。');
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . '/' . hash('sha256', $key) . '.json';
    }

    /** @param resource $handle
     *  @return array<string, int>
     */
    private function read($handle): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        if (!is_string($contents) || $contents === '') {
            return [];
        }
        $state = json_decode($contents, true);

        return is_array($state) ? $state : [];
    }

    /** @param resource $handle */
    private function write($handle, int $startedAt, int $attempts): void
    {
        $contents = json_encode([
            'started_at' => $startedAt,
            'attempts' => $attempts,
        ], JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) {
            throw new \RuntimeException('无法保存限速状态。');
        }
    }
}
