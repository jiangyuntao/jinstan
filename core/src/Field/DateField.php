<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

use DateTimeImmutable;

final class DateField implements FieldType
{
    public function normalize(mixed $value): string
    {
        return trim((string) $value);
    }
    public function valid(mixed $value): bool
    {
        if (!is_scalar($value) || (string) $value === '') {
            return true;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
    public function adminView(): string
    {
        return 'fields/date.jst';
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
