<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class RangeField implements FieldType
{
    public function __construct(
        private readonly int|float $min = 0,
        private readonly int|float $max = 100,
        private readonly int|float $step = 1,
    ) {
    }

    public function normalize(mixed $value): int|float
    {
        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    public function valid(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= $this->min && (float) $value <= $this->max;
    }

    public function adminView(): string
    {
        return 'fields/range.jst';
    }
    public function adminData(): array
    {
        return ['min' => $this->min, 'max' => $this->max, 'step' => $this->step];
    }
    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
