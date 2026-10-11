<?php

declare(strict_types=1);

namespace Jinstan\Core\Security;

/** Public API：为登录和公开 Plugin Route 提供跨请求限速。 */
interface RateLimiter
{
    public function consume(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult;

    public function clear(string $key): void;
}
