<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

use JsonException;

final class JsonField implements FieldType
{
    public function normalize(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (trim((string) $value) === '') {
            return [];
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function valid(mixed $value): bool
    {
        if (is_array($value) || (is_scalar($value) && trim((string) $value) === '')) {
            return true;
        }
        if (!is_scalar($value)) {
            return false;
        }

        try {
            return is_array(json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return false;
        }
    }

    public function adminView(): string
    {
        return 'fields/json.jst';
    }
    public function adminData(): array
    {
        return ['rows' => 12];
    }

    public function adminValue(mixed $value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
