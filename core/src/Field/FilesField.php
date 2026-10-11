<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

class FilesField extends MediaField
{
    public function normalize(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => trim((string) $item),
            is_array($value) ? $value : [],
        ));
    }

    public function valid(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $path) {
            if (!\Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage::isStoredPath($path)) {
                return false;
            }
        }

        return true;
    }

    public function adminView(): string
    {
        return 'fields/files.jst';
    }
    public function allowsMultiple(): bool
    {
        return true;
    }
    public function imagesOnly(): bool
    {
        return false;
    }
}
