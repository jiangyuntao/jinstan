<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Services;

use Jinstan\Core\Application\Content\TemplateCatalogReader;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentRepositoryContract;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Event\ContentCreated;
use Jinstan\Core\Event\ContentPublished;
use Jinstan\Core\Event\ContentUnpublished;
use Jinstan\Core\Event\ContentUpdated;
use Jinstan\Core\Event\EventDispatcher;

final class ContentService
{
    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly ContentRepositoryContract $contents,
        private readonly ?\Jinstan\Core\Taxonomy\TaxonomyRepositoryContract $taxonomy = null,
        private readonly ?TemplateCatalogReader $templates = null,
        private readonly ?\Jinstan\Core\Routing\ProtectedSlug $protectedSlugs = null,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    /** Public API：内容及当前启用分组的关系一起保存，校验失败时不写入。 */
    public function saveWithTerms(string $type, ?int $id, ContentTranslation $translation, array $data, array $terms, ?int $authorId, ?int $parentId, bool $published, ?string $template = null): Content
    {
        $taxonomy = $this->taxonomy ?? throw new \LogicException('分类服务未配置。');
        if ($id !== null && $this->contents->find($id)?->type !== $type) {
            throw new \InvalidArgumentException('内容类型不匹配。');
        }
        if ($template !== null && $template !== '') {
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,99}$/D', $template)) {
                throw new \InvalidArgumentException('详情模板无效。');
            }
            $this->templates?->validate($template, 'detail');
        }
        $selections = $taxonomy->validateSelections($this->types->get($type)->taxonomies, $terms);

        return $taxonomy->transaction(function () use ($type, $id, $translation, $data, $selections, $authorId, $parentId, $published, $taxonomy, $template): Content {
            if ($id === null) {
                $content = $this->create($type, $translation, $data, $authorId, $parentId);
            } else {
                $this->update($id, $translation, $data);
                $this->setParent($id, $parentId);
                $content = $this->contents->find($id);
            }
            if ($template !== null) {
                $this->contents->update(new Content($content->id, $content->type, $content->parentId, $content->status, $template === '' ? null : $template, $content->authorId, $content->data));
            }
            $taxonomy->sync($content->id, $selections);
            $this->setPublished($content->id, $published);

            return $this->contents->find($content->id);
        });
    }

    /** @param array<string, mixed> $data */
    public function create(string $type, ContentTranslation $translation, array $data = [], ?int $authorId = null, ?int $parentId = null): Content
    {
        $definition = $this->types->get($type);
        $this->protectedSlugs?->assertContentPathAvailable($type, $translation->slug);
        if ($parentId !== null) {
            $this->assertValidParent($type, 0, $parentId);
        }
        [$shared, $localized] = $this->processFields($definition, $data, $translation->data);

        $processedTranslation = new ContentTranslation($translation->locale, $translation->title, $translation->slug, $translation->excerpt, $localized, $translation->seoTitle, $translation->metaDescription);
        $content = $this->contents->create(
            new Content(0, $type, $parentId, 'draft', null, $authorId, $shared),
            $processedTranslation,
        );
        $this->events?->dispatch(new ContentCreated($content, $processedTranslation));

        return $content;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, ContentTranslation $translation, array $data): Content
    {
        $content = $this->contents->find($id) ?? throw new \InvalidArgumentException('内容不存在。');
        $this->protectedSlugs?->assertContentPathAvailable($content->type, $translation->slug);
        [$shared, $localized] = $this->processFields($this->types->get($content->type), $data, $translation->data);
        $updated = new Content($content->id, $content->type, $content->parentId, $content->status, $content->template, $content->authorId, $shared);
        $this->contents->update($updated);
        $processedTranslation = new ContentTranslation($translation->locale, $translation->title, $translation->slug, $translation->excerpt, $localized, $translation->seoTitle, $translation->metaDescription);
        $this->contents->saveTranslation($id, $processedTranslation);
        $this->events?->dispatch(new ContentUpdated($updated, $processedTranslation));

        return $updated;
    }

    public function setParent(int $id, ?int $parentId): void
    {
        $content = $this->contents->find($id) ?? throw new \InvalidArgumentException('内容不存在。');
        if ($parentId !== null) {
            $this->assertValidParent($content->type, $id, $parentId);
        }

        $this->contents->update(new Content(
            $content->id,
            $content->type,
            $parentId,
            $content->status,
            $content->template,
            $content->authorId,
            $content->data,
        ));
    }

    public function publish(int $id): void
    {
        $this->setPublished($id, true);
    }

    public function setPublished(int $id, bool $published): void
    {
        $before = $this->contents->find($id) ?? throw new \InvalidArgumentException('内容不存在。');
        $targetStatus = $published ? 'published' : 'draft';
        if ($before->status === $targetStatus) {
            return;
        }
        $this->contents->setPublished($id, $published);
        $content = $this->contents->find($id) ?? throw new \LogicException('内容状态更新后无法读取。');
        $this->events?->dispatch($published ? new ContentPublished($content) : new ContentUnpublished($content));
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $translationData
     *  @return array{array<string, mixed>, array<string, mixed>}
     */
    private function processFields(ContentTypeDefinition $definition, array $data, array $translationData): array
    {
        $shared = [];
        $localized = [];

        foreach ($definition->fields as $name => $field) {
            $value = $field->process($field->translatable ? ($translationData[$name] ?? $data[$name] ?? null) : ($data[$name] ?? null));
            if ($field->translatable) {
                $localized[$name] = $value;
            } else {
                $shared[$name] = $value;
            }
        }

        return [$shared, $localized];
    }

    private function assertValidParent(string $type, int $contentId, int $parentId): void
    {
        if ($contentId === $parentId) {
            throw new \InvalidArgumentException('内容不能将自身设置为上级内容。');
        }
        $parent = $this->contents->find($parentId);
        if ($parent === null || $parent->type !== $type) {
            throw new \InvalidArgumentException('上级内容必须属于同一内容类型。');
        }
        while ($parent->parentId !== null) {
            if ($parent->parentId === $contentId) {
                throw new \InvalidArgumentException('上级内容不能形成循环层级。');
            }
            $parent = $this->contents->find($parent->parentId)
                ?? throw new \InvalidArgumentException('上级内容不存在。');
        }
    }
}
