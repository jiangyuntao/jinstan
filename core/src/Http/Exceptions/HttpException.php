<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Exceptions;

use RuntimeException;

abstract class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
