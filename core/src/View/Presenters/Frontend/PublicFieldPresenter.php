<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Frontend;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentAttachmentPath;
use Jinstan\Core\View\ContentBodyView;
use Jinstan\Core\Field\BooleanField;
use Jinstan\Core\Field\ContentBodyField;
use Jinstan\Core\Field\FileField;
use Jinstan\Core\Field\FilesField;
use Jinstan\Core\Field\ImageField;
use Jinstan\Core\Field\ImagesField;
use Jinstan\Core\Field\JsonField;
use Jinstan\Core\Field\SkuField;
use Jinstan\Core\Field\SwitchField;
use Latte\Runtime\Html;

final class PublicFieldPresenter
{
    public function __construct(private readonly ContentBodyView $body)
    {
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    public function presentListData(ContentTypeDefinition $type, array $data): array
    {
        foreach ($type->fields as $field) {
            if (!array_key_exists($field->name, $data) || $this->empty($data[$field->name])) {
                continue;
            }
            if ($field->type instanceof ImagesField || $field->type instanceof FilesField) {
                $data[$field->name] = array_map($this->mediaUrl(...), $data[$field->name]);
            } elseif ($field->type instanceof ImageField || $field->type instanceof FileField) {
                $data[$field->name] = $this->mediaUrl($data[$field->name]);
            }
        }

        return $data;
    }

    /** @return list<array{name: string, label: string, kind: string, value: mixed}> */
    public function present(ContentTypeDefinition $type, Content $content, ContentTranslation $translation): array
    {
        $items = [];
        foreach ($type->fields as $field) {
            $source = $field->translatable ? $translation->data : $content->data;
            $value = $source[$field->name] ?? null;
            if ($this->empty($value)) {
                continue;
            }

            $kind = 'text';
            if ($field->type instanceof ContentBodyField) {
                $kind = 'html';
                $value = new Html($this->body->render($value));
            } elseif ($field->type instanceof ImagesField) {
                $kind = 'images';
                $value = array_map($this->mediaUrl(...), $value);
            } elseif ($field->type instanceof ImageField) {
                $kind = 'image';
                $value = $this->mediaUrl($value);
            } elseif ($field->type instanceof FilesField) {
                $kind = 'files';
                $value = array_map($this->mediaUrl(...), $value);
            } elseif ($field->type instanceof FileField) {
                $kind = 'file';
                $value = $this->mediaUrl($value);
            } elseif ($field->type instanceof SkuField) {
                $kind = 'sku';
            } elseif ($field->type instanceof BooleanField || $field->type instanceof SwitchField) {
                $kind = 'boolean';
            } elseif ($field->type instanceof JsonField) {
                $kind = 'json';
                $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } elseif (is_array($value)) {
                $kind = 'list';
            }

            $items[] = ['name' => $field->name, 'label' => $field->label, 'kind' => $kind, 'value' => $value];
        }

        return $items;
    }

    private function empty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function mediaUrl(mixed $value): string
    {
        $path = (string) $value;

        return ContentAttachmentPath::isValid($path) ? '/media/' . $path : $path;
    }
}
