<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Queries;

use Jinstan\Core\Content\ContentReadRepository;

final class ContentEditQuery
{
    public function __construct(private readonly ContentReadRepository $contents)
    {
    }

    /** @return array<string, mixed>|null */
    public function formInput(string $type, int $id, string $locale): ?array
    {
        $content = $this->contents->find($id);
        if ($content === null || $content->type !== $type) {
            return null;
        }

        $translation = $this->contents->translation($id, $locale);

        return [
            'locale' => $locale,
            'template' => $content->template ?? '',
            'title' => $translation?->title ?? '',
            'slug' => $translation?->slug ?? '',
            'published' => $content->status === 'published',
            'parent_id' => $content->parentId,
            'data' => array_merge($content->data, $translation?->data ?? []),
        ];
    }
}
