<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Services;

use Jinstan\Core\Content\ContentRepositoryContract;
use Jinstan\Core\Content\ContentAttachmentPath;
use Jinstan\Core\Content\ContentAttachmentStore;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Field\MediaField;

final class ContentTypeDeletionService
{
    private const PROTECTED_TYPES = ['article', 'page', 'product'];

    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly EditableContentTypes $editable,
        private readonly ContentRepositoryContract $contents,
        private readonly ContentAttachmentStore $attachments,
    ) {
    }

    public function deletable(string $handle): bool
    {
        return !in_array($handle, self::PROTECTED_TYPES, true);
    }

    public function delete(string $handle): void
    {
        $type = $this->types->get($handle);
        if (!$this->deletable($handle)) {
            throw new \InvalidArgumentException('内置内容类型不能删除。');
        }

        $attachmentPaths = $this->attachmentPaths($type);
        $remainingDefinitions = [];
        foreach ($this->types->all() as $definition) {
            if ($definition->handle !== $handle) {
                $remainingDefinitions[] = $this->editable->export($definition);
            }
        }

        $this->contents->transaction(function () use ($handle, $remainingDefinitions): void {
            foreach ($this->contents->list($handle) as $content) {
                $this->contents->delete($content->id);
            }
            $this->editable->save($remainingDefinitions);
        });

        foreach ($attachmentPaths as $path) {
            if (!$this->attachmentReferenced($path, $handle)) {
                $this->attachments->delete($path);
            }
        }
    }

    /** @return list<string> */
    private function attachmentPaths(ContentTypeDefinition $type): array
    {
        $paths = [];
        foreach ($this->contents->list($type->handle) as $content) {
            $paths = [...$paths, ...$this->pathsFromData($type, $content->data, false)];
            foreach ($this->contents->translations($content->id) as $translation) {
                $paths = [...$paths, ...$this->pathsFromData($type, $translation->data, true)];
            }
        }

        return array_values(array_unique($paths));
    }

    /** @return list<string> */
    private function pathsFromData(ContentTypeDefinition $type, array $data, bool $translated): array
    {
        $paths = [];
        foreach ($type->fields as $field) {
            if (!$field->type instanceof MediaField || $field->translatable !== $translated) {
                continue;
            }
            $values = $field->type->allowsMultiple() ? (array) ($data[$field->name] ?? []) : [$data[$field->name] ?? null];
            foreach ($values as $value) {
                if (ContentAttachmentPath::isValid($value)) {
                    $paths[] = $value;
                }
            }
        }

        return $paths;
    }

    private function attachmentReferenced(string $path, string $deletedType): bool
    {
        foreach ($this->types->all() as $type) {
            if ($type->handle === $deletedType) {
                continue;
            }
            foreach ($this->contents->list($type->handle) as $content) {
                if (in_array($path, $this->pathsFromData($type, $content->data, false), true)) {
                    return true;
                }
                foreach ($this->contents->translations($content->id) as $translation) {
                    if (in_array($path, $this->pathsFromData($type, $translation->data, true), true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
