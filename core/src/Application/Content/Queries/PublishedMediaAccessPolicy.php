<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Queries;

use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\PublicMediaAccessPolicy;
use Jinstan\Core\Field\MediaField;

final class PublishedMediaAccessPolicy implements PublicMediaAccessPolicy
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly ContentTypeRegistry $types,
    ) {
    }

    public function allows(string $path): bool
    {
        foreach ($this->types->all() as $type) {
            $mediaFields = array_filter(
                $type->fields,
                static fn ($field): bool => $field->type instanceof MediaField,
            );
            if ($mediaFields === []) {
                continue;
            }
            foreach ($this->contents->list($type->handle) as $content) {
                if ($content->status !== 'published') {
                    continue;
                }
                foreach ($mediaFields as $field) {
                    if (!$field->translatable && $this->contains($content->data[$field->name] ?? null, $path)) {
                        return true;
                    }
                    if (!$field->translatable) {
                        continue;
                    }
                    foreach ($this->contents->translations($content->id) as $translation) {
                        if ($this->contains($translation->data[$field->name] ?? null, $path)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function contains(mixed $value, string $path): bool
    {
        if (is_string($value)) {
            return hash_equals($path, $value);
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (is_string($item) && hash_equals($path, $item)) {
                return true;
            }
        }

        return false;
    }
}
