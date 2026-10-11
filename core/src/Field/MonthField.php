<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

use DateTimeImmutable;

final class MonthField implements FieldType
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
        $month = DateTimeImmutable::createFromFormat('!Y-m', (string) $value);

        return $month !== false && $month->format('Y-m') === $value;
    }

    public function adminView(): string
    {
        return 'fields/month.jst';
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
