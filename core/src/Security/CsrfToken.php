<?php

declare(strict_types=1);

namespace Jinstan\Core\Security;

final class CsrfToken implements CsrfTokenProvider
{
    private const KEY = '_jinstan_csrf';

    public function value(): string
    {
        if (!isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public function valid(?string $token): bool
    {
        return is_string($token) && hash_equals($this->value(), $token);
    }

    public function regenerate(): string
    {
        return $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
