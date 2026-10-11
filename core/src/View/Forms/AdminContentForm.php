<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Forms;

use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Field\MediaField;
use Jinstan\Core\Field\SkuField;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;

final class AdminContentForm
{
    public function __construct(private readonly UrlGenerator $urls)
    {
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    /** @param array<int, string> $parents */
    public function data(ContentTypeDefinition $type, CsrfToken $csrf, array $input = [], ?int $id = null, array $parents = []): array
    {
        $values = is_array($input['data'] ?? null) ? $input['data'] : [];
        $fields = [];
        foreach ($type->fields as $field) {
            $value = $field->type->adminValue($values[$field->name] ?? $field->default);
            $formField = [
                'name' => $field->name,
                'inputName' => 'data[' . $field->name . ']',
                'label' => $field->label,
                'view' => $field->type->adminView(),
                'value' => $value,
                'required' => $field->required,
            ] + $field->type->adminData() + $field->settings;
            if ($field->type instanceof MediaField) {
                $attachments = $field->type->allowsMultiple() ? (is_array($value) ? $value : []) : [$value];
                $formField['attachments'] = array_values(array_filter(array_map(function (mixed $attachment): ?array {
                    if (is_string($attachment) && $attachment !== '') {
                        return [
                            'name' => basename($attachment),
                            'url' => $this->mediaUrl($attachment),
                            'path' => $attachment,
                        ];
                    }

                    return null;
                }, $attachments)));
                $formField['imagesOnly'] = $field->type->imagesOnly();
                $formField['previewJson'] = json_encode(
                    array_values(array_map(
                        static fn (array $attachment): array => [
                            'url' => $attachment['url'],
                            'path' => $attachment['path'],
                        ],
                        array_filter($formField['attachments'], static fn (array $attachment): bool => is_string($attachment['url'])),
                    )),
                    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
                );
            }
            if ($field->type instanceof SkuField) {
                $formField['rowsJson'] = json_encode(
                    $value === [] ? [['title' => '', 'code' => '', 'stock' => 0, 'price' => 0]] : $value,
                    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
                );
            }
            $fields[] = $formField;
        }

        return [
            'typeHandle' => $type->handle,
            'action' => $id === null
                ? $this->urls->route('admin.content.store', ['type' => $type->handle])
                : $this->urls->route('admin.content.update', ['type' => $type->handle, 'id' => $id]),
            'deleteAction' => $id === null ? null : $this->urls->route('admin.content.delete', ['type' => $type->handle, 'id' => $id]),
            'csrfToken' => $csrf->value(),
            'title' => (string) ($input['title'] ?? ''),
            'slug' => (string) ($input['slug'] ?? ''),
            'published' => array_key_exists('published', $input)
                && filter_var($input['published'], FILTER_VALIDATE_BOOL),
            'fields' => $fields,
            'hierarchical' => $type->hierarchical,
            'parentId' => isset($input['parent_id']) && (int) $input['parent_id'] > 0 ? (int) $input['parent_id'] : 0,
            'parents' => $parents,
            'parentSearchUrl' => $this->urls->route('admin.content.parents.search', ['type' => $type->handle]),
        ];
    }

    private function mediaUrl(string $path): string
    {
        $parts = explode('/', $path, 3);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Stored media path is invalid.');
        }

        return $this->urls->route('admin.media.show', [
            'first' => $parts[0],
            'second' => $parts[1],
            'file' => $parts[2],
        ]);
    }
}
