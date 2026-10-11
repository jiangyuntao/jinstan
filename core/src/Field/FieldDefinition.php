<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

use InvalidArgumentException;

final class FieldDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly FieldType $type,
        public readonly bool $required = false,
        public readonly bool $translatable = false,
        public readonly mixed $default = null,
        /** @var array<string, mixed> */
        public readonly array $settings = [],
    ) {
    }

    public function process(mixed $value): mixed
    {
        $value ??= $this->default;

        if ($value === null && !$this->required) {
            return null;
        }

        if (!$this->type->valid($value)) {
            throw new InvalidArgumentException(sprintf('字段“%s”的值无效。', $this->label));
        }

        $value = $this->type->normalize($value);
        if ($this->required && ($value === '' || $value === null || (is_array($value) && $value === []))) {
            throw new InvalidArgumentException(sprintf('字段“%s”不能为空。', $this->label));
        }

        return $value;
    }
}
