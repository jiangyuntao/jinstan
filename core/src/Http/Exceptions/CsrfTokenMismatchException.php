<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Exceptions;

final class CsrfTokenMismatchException extends HttpException
{
    public function __construct(string $message = 'CSRF Token Mismatch')
    {
        parent::__construct(419, $message);
    }
}
