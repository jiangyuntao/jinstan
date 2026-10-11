<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class TextField implements FieldType
{
    public function normalize(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function valid(mixed $value): bool
    {
        return is_scalar($value);
    }

    public function adminView(): string
    {
        return 'fields/text.jst';
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
