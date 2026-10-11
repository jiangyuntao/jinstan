<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class PhoneField implements FieldType
{
    public function normalize(mixed $value): string
    {
        return trim((string) $value);
    }

    public function valid(mixed $value): bool
    {
        return is_scalar($value) && ((string) $value === '' || preg_match('/^[0-9+() .-]{5,32}$/', (string) $value) === 1);
    }

    public function adminView(): string
    {
        return 'fields/phone.jst';
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
