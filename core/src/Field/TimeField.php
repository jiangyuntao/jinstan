<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

use DateTimeImmutable;

final class TimeField implements FieldType
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
        $time = DateTimeImmutable::createFromFormat('!H:i', (string) $value);

        return $time !== false && $time->format('H:i') === $value;
    }

    public function adminView(): string
    {
        return 'fields/time.jst';
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
