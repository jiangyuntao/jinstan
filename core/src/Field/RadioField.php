<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class RadioField implements FieldType
{
    /** @param array<string, string> $options */
    public function __construct(private readonly array $options)
    {
    }
    public function normalize(mixed $value): string
    {
        return (string) $value;
    }
    public function valid(mixed $value): bool
    {
        return is_scalar($value) && ((string) $value === '' || array_key_exists((string) $value, $this->options));
    }
    public function adminView(): string
    {
        return 'fields/radio.jst';
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
