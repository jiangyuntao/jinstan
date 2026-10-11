<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Validation;

use Jinstan\Core\Http\Exceptions\ValidationException;

final class ValidationResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private readonly array $data,
        private readonly array $errors = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return null;
    }

    /**
     * Normalized data is suitable for form redisplay, but must not be passed to
     * a write operation until validated() succeeds.
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        if (!$this->isValid()) {
            throw new ValidationException($this->errors, '表单验证失败。');
        }

        return $this->data;
    }
}
