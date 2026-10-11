<?php

declare(strict_types=1);

namespace Jinstan\Core\Security;

final readonly class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int $retryAfter,
    ) {
    }
}
