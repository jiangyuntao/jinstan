<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class BooleanField implements FieldType
{
    public function normalize(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function valid(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    public function adminView(): string
    {
        return 'fields/boolean.jst';
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
