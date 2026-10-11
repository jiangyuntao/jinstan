<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

interface FieldTypeRegistrar
{
    /**
     * @param class-string<FieldType> $fieldClass
     * @param callable(array<string, mixed>): FieldType $factory
     */
    public function register(string $handle, string $fieldClass, callable $factory): void;
}
