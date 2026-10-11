<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\FileField;
use Jinstan\Core\Field\ImageField;
use Jinstan\Core\Field\ImagesField;
use Jinstan\Core\Field\SkuField;
use Jinstan\Core\Field\ContentBodyField;
use Jinstan\Core\Field\TextareaField;

final class DefaultContentTypes
{
    public static function register(ContentTypeRegistry $types): void
    {
        $contentBody = new ContentBodyField();
        $types->register(new ContentTypeDefinition('article', '文章', [
            'content' => new FieldDefinition('content', '正文', $contentBody, translatable: true, settings: ['rows' => 16]),
            'cover_image' => new FieldDefinition('cover_image', '封面图', new FileField(), translatable: true),
        ], 'article-detail', '/blog/{slug}', taxonomies: [
            'category' => ['multiple' => true, 'required' => false],
        ], listEnabled: true, listPath: '/blog', listTemplate: 'article-list'));
        $types->register(new ContentTypeDefinition('page', '页面', [
            'content' => new FieldDefinition('content', '正文', new ContentBodyField(true), translatable: true, settings: ['rows' => 16]),
        ], 'page-detail', '/{slug}', true));
        $types->register(new ContentTypeDefinition('product', '产品', [
            'description' => new FieldDefinition('description', '描述', new TextareaField(), translatable: true),
            'sku' => new FieldDefinition('sku', 'SKU', new SkuField(), required: true, translatable: true),
            'cover_image' => new FieldDefinition('cover_image', '主图', new ImageField()),
            'gallery' => new FieldDefinition('gallery', '产品图库', new ImagesField()),
            'content' => new FieldDefinition('content', '图文介绍', new ContentBodyField(true), translatable: true, settings: ['rows' => 16]),
        ], 'product-detail', '/product/{slug}', listEnabled: true, listPath: '/products', listTemplate: 'product-list'));
    }

    /** @return array<string, list<string>> */
    public static function protectedFieldNames(): array
    {
        return [
            'article' => ['content', 'cover_image'],
            'page' => ['content'],
            'product' => ['description', 'sku', 'cover_image', 'gallery', 'content'],
        ];
    }

    public static function assertProtectedFields(ContentTypeDefinition $definition): void
    {
        $names = self::protectedFieldNames()[$definition->handle] ?? null;
        if ($names === null) {
            return;
        }

        $defaults = new ContentTypeRegistry();
        self::register($defaults);
        $protectedFields = $defaults->get($definition->handle)->fields;

        foreach ($names as $name) {
            $field = $definition->fields[$name] ?? null;
            $expected = $protectedFields[$name];
            if ($field === null) {
                throw new \InvalidArgumentException(sprintf('内置字段“%s”不能删除或改名。', $expected->label));
            }
            if ($field->type::class !== $expected->type::class || $field->translatable !== $expected->translatable) {
                throw new \InvalidArgumentException(sprintf('内置字段“%s”的标识、类型和翻译设置不能修改。', $expected->label));
            }
            if ($field->type instanceof ContentBodyField
                && $field->type->allowsHtmlDocument() !== $expected->type->allowsHtmlDocument()) {
                throw new \InvalidArgumentException(sprintf('内置字段“%s”的 HTML 页面设置不能修改。', $expected->label));
            }
        }
    }
}
