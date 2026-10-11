<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Exceptions;

final class ValidationException extends HttpException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        private readonly array $errors = [],
        string $message = 'Validation Failed',
    ) {
        parent::__construct(422, $message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
