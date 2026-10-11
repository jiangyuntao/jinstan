<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class NumberField implements FieldType
{
    public function normalize(mixed $value): int|float
    {
        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    public function valid(mixed $value): bool
    {
        return is_numeric($value);
    }

    public function adminView(): string
    {
        return 'fields/number.jst';
    }

    public function adminData(): array
    {
        return ['step' => 'any'];
    }
    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
