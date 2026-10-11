<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class TagsField implements FieldType
{
    public function normalize(mixed $value): array
    {
        $tags = is_array($value) ? $value : explode(',', (string) $value);
        $tags = array_map(static fn (mixed $tag): string => trim((string) $tag), $tags);
        $tags = array_filter($tags, static fn (string $tag): bool => $tag !== '');

        return array_values(array_unique($tags));
    }

    public function valid(mixed $value): bool
    {
        if (!is_scalar($value) && !is_array($value)) {
            return false;
        }
        foreach (is_array($value) ? $value : explode(',', (string) $value) as $tag) {
            if (!is_scalar($tag) || mb_strlen(trim((string) $tag)) > 100) {
                return false;
            }
        }

        return true;
    }

    public function adminView(): string
    {
        return 'fields/tags.jst';
    }
    public function adminData(): array
    {
        return [];
    }

    public function adminValue(mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }
}
