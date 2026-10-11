<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class WeekField implements FieldType
{
    public function normalize(mixed $value): string
    {
        return trim((string) $value);
    }

    public function valid(mixed $value): bool
    {
        return is_scalar($value) && ((string) $value === '' || preg_match('/^\\d{4}-W(?:0[1-9]|[1-4]\\d|5[0-3])$/', (string) $value) === 1);
    }

    public function adminView(): string
    {
        return 'fields/week.jst';
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
