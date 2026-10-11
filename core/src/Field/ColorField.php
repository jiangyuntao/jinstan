<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class ColorField implements FieldType
{
    public function normalize(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    public function valid(mixed $value): bool
    {
        return is_scalar($value) && ((string) $value === '' || preg_match('/^#[a-fA-F0-9]{6}$/', (string) $value) === 1);
    }

    public function adminView(): string
    {
        return 'fields/color.jst';
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
