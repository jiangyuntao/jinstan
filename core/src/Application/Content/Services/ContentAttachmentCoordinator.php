<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Services;

use Jinstan\Core\Application\Content\ContentUploadInput;
use Jinstan\Core\Content\ContentAttachmentPath;
use Jinstan\Core\Content\ContentAttachmentStore;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Field\MediaField;
use Psr\Http\Message\UploadedFileInterface;

final class ContentAttachmentCoordinator
{
    public function __construct(
        private readonly ContentAttachmentStore $storage,
        private readonly ContentReadRepository $contents,
        private readonly ContentTypeRegistry $types,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, UploadedFileInterface|array<mixed>> $uploadedFiles
     * @param list<string> $created
     * @param array<string, mixed> $existing
     * @return array{array<string, mixed>, list<string>}
     */
    public function prepare(array $uploadedFiles, ContentTypeDefinition $type, array $input, array &$created, array $existing = []): array
    {
        $data = is_array($input['data'] ?? null) ? $input['data'] : [];
        $replaced = [];
        $uploadedData = $uploadedFiles['data'] ?? [];
        if (!is_array($uploadedData)) {
            throw new \InvalidArgumentException('上传文件格式无效。');
        }
        $uploads = new ContentUploadInput();

        foreach ($type->fields as $field) {
            if (!$field->type instanceof MediaField) {
                continue;
            }
            unset($data[$field->name]);
            $removeMedia = is_array($input['remove_media'] ?? null)
                && ($input['remove_media'][$field->name] ?? null) === '1';
            $files = $uploads->files($uploadedData[$field->name] ?? null, $field->type->allowsMultiple(), $field->label);
            $managesMultipleImages = $field->type->allowsMultiple()
                && $field->type->imagesOnly()
                && is_array($input['manage_media'] ?? null)
                && ($input['manage_media'][$field->name] ?? null) === '1';
            if ($managesMultipleImages) {
                $existingPaths = $this->pathsForValue($existing[$field->name] ?? null, true);
                $mediaOrder = is_array($input['media_order'] ?? null) ? $input['media_order'] : [];
                $orderTokens = is_array($mediaOrder[$field->name] ?? null) ? $mediaOrder[$field->name] : [];
                $keptPaths = [];
                foreach ($orderTokens as $token) {
                    if (is_string($token) && in_array($token, $existingPaths, true) && !in_array($token, $keptPaths, true)) {
                        $keptPaths[] = $token;
                    }
                }
                $replaced = [...$replaced, ...array_values(array_diff($existingPaths, $keptPaths))];
                $data[$field->name] = $keptPaths;
                if ($files === []) {
                    continue;
                }
            }
            if ($files === []) {
                if ($removeMedia) {
                    $data[$field->name] = $field->type->allowsMultiple() ? [] : null;
                    $replaced = [...$replaced, ...$this->pathsForValue($existing[$field->name] ?? null, $field->type->allowsMultiple())];
                    continue;
                }
                if (array_key_exists($field->name, $existing)) {
                    $data[$field->name] = $existing[$field->name];
                }
                continue;
            }
            $paths = [];
            foreach ($files as $file) {
                $streamPath = $file->getStream()->getMetadata('uri');
                if (!is_string($streamPath) || $streamPath === '') {
                    throw new \InvalidArgumentException('上传临时文件不可用。');
                }
                $path = $this->storage->store($streamPath, $file->getClientFilename() ?? 'upload', $field->type->imagesOnly());
                $paths[] = $path;
                $created[] = $path;
            }
            if ($field->type->allowsMultiple()) {
                if ($managesMultipleImages) {
                    $orderedPaths = [];
                    $pendingPaths = $paths;
                    foreach ($orderTokens as $token) {
                        if ($token === 'new' && $pendingPaths !== []) {
                            $orderedPaths[] = array_shift($pendingPaths);
                        } elseif (is_string($token) && in_array($token, (array) ($data[$field->name] ?? []), true)) {
                            $orderedPaths[] = $token;
                        }
                    }
                    $data[$field->name] = [...$orderedPaths, ...$pendingPaths];
                } else {
                    $data[$field->name] = [...(array) ($data[$field->name] ?? []), ...$paths];
                }
                if (!$field->type->imagesOnly()) {
                    $replaced = [...$replaced, ...$this->pathsForValue($existing[$field->name] ?? null, true)];
                }
            } else {
                $data[$field->name] = $paths[0];
                $replaced = [...$replaced, ...$this->pathsForValue($existing[$field->name] ?? null, false)];
            }
        }

        return [$data, array_values(array_unique($replaced))];
    }

    /** @param list<ContentTranslation> $translations
     *  @return list<string>
     */
    public function pathsForContent(ContentTypeDefinition $type, array $sharedData, array $translations): array
    {
        $paths = [];
        foreach ($type->fields as $field) {
            if (!$field->type instanceof MediaField) {
                continue;
            }
            if (!$field->translatable) {
                $paths = [...$paths, ...$this->pathsForValue($sharedData[$field->name] ?? null, $field->type->allowsMultiple())];
                continue;
            }
            foreach ($translations as $translation) {
                $paths = [...$paths, ...$this->pathsForValue($translation->data[$field->name] ?? null, $field->type->allowsMultiple())];
            }
        }

        return array_values(array_unique($paths));
    }

    /** @param list<string> $paths */
    public function deleteUnreferenced(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            if (!$this->isReferenced($path)) {
                $this->storage->delete($path);
            }
        }
    }

    /** @return list<string> */
    private function pathsForValue(mixed $value, bool $multiple): array
    {
        $paths = [];
        foreach ($multiple ? (array) $value : [$value] as $path) {
            if (ContentAttachmentPath::isValid($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function isReferenced(string $path): bool
    {
        foreach ($this->types->all() as $type) {
            foreach ($this->contents->list($type->handle) as $content) {
                if (in_array($path, $this->pathsForContent($type, $content->data, $this->contents->translations($content->id)), true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
