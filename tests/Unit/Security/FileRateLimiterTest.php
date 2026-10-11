<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Security;

use Jinstan\Core\Infrastructure\Filesystem\Security\FileRateLimiter;
use PHPUnit\Framework\TestCase;

final class FileRateLimiterTest extends TestCase
{
    public function testItRejectsAttemptsOverTheLimitAndCanBeCleared(): void
    {
        $directory = sys_get_temp_dir() . '/jinstan-rate-limit-' . bin2hex(random_bytes(6));
        $limiter = new FileRateLimiter($directory);

        self::assertTrue($limiter->consume('login:test', 2, 60)->allowed);
        self::assertTrue($limiter->consume('login:test', 2, 60)->allowed);
        $blocked = $limiter->consume('login:test', 2, 60);
        self::assertFalse($blocked->allowed);
        self::assertGreaterThan(0, $blocked->retryAfter);

        $limiter->clear('login:test');
        self::assertTrue($limiter->consume('login:test', 2, 60)->allowed);
    }
}
