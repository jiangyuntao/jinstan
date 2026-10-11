<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class UrlField implements FieldType
{
    public function normalize(mixed $value): string
    {
        return trim((string) $value);
    }
    public function valid(mixed $value): bool
    {
        return is_scalar($value) && ((string) $value === '' || filter_var((string) $value, FILTER_VALIDATE_URL) !== false);
    }
    public function adminView(): string
    {
        return 'fields/url.jst';
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
