<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class CheckboxGroupField implements FieldType
{
    /** @param array<string, string> $options */
    public function __construct(private readonly array $options)
    {
    }
    public function normalize(mixed $value): array
    {
        return array_values(array_map('strval', is_array($value) ? $value : []));
    }
    public function valid(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_scalar($item) || !array_key_exists((string) $item, $this->options)) {
                return false;
            }
        }
        return true;
    }
    public function adminView(): string
    {
        return 'fields/checkbox-group.jst';
    }
    public function adminData(): array
    {
        return ['options' => $this->options];
    }
    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
