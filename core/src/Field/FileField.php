<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

class FileField extends MediaField
{
    public function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    public function valid(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || \Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage::isStoredPath($value);
    }

    public function adminView(): string
    {
        return 'fields/file.jst';
    }
    public function allowsMultiple(): bool
    {
        return false;
    }
    public function imagesOnly(): bool
    {
        return false;
    }
}
