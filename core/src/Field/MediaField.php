<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

abstract class MediaField implements FieldType
{
    abstract public function allowsMultiple(): bool;

    abstract public function imagesOnly(): bool;

    public function adminData(): array
    {
        return [
            'multiple' => $this->allowsMultiple(),
            'accept' => $this->imagesOnly() ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/webp,application/pdf',
        ];
    }

    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
