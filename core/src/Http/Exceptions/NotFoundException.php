<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Exceptions;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found')
    {
        parent::__construct(404, $message);
    }
}
