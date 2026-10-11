<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class EmailField implements FieldType
{
    public function normalize(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }
    public function valid(mixed $value): bool
    {
        return is_scalar($value) && ((string) $value === '' || filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false);
    }
    public function adminView(): string
    {
        return 'fields/email.jst';
    }
    public function adminData(): array
    {
        return [];
    }
    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
