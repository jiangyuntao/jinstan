<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Exceptions;

final class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct(403, $message);
    }
}
