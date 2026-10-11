<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Validation;

interface Validator
{
    /** @param array<string, mixed> $input */
    public function validate(array $input): ValidationResult;
}
