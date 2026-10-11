<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Exceptions;

final class AuthenticationException extends HttpException
{
    public function __construct(string $message = 'Unauthenticated')
    {
        parent::__construct(401, $message);
    }
}
