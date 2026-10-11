<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class TextareaField implements FieldType
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
        return 'fields/textarea.jst';
    }
    public function adminData(): array
    {
        return ['rows' => 8];
    }
    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
