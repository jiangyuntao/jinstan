<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

use Jinstan\Core\Http\Exceptions\ValidationException;

final class SafeErrorMessage
{
    public static function from(\Throwable $exception, string $fallback): string
    {
        if (!$exception instanceof ValidationException
            && !$exception instanceof \InvalidArgumentException
            && !$exception instanceof \DomainException
            && !$exception instanceof \OutOfBoundsException
        ) {
            return $fallback;
        }

        $message = trim($exception->getMessage());

        return $message === '' ? $fallback : mb_substr($message, 0, 300);
    }
}
