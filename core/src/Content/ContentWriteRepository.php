<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

/** Public API：供 Content Application Service 使用的最小写入契约。 */
interface ContentWriteRepository
{
    public function create(Content $content, ContentTranslation $translation): Content;

    public function update(Content $content): void;

    public function saveTranslation(int $contentId, ContentTranslation $translation): void;

    public function setPublished(int $id, bool $published): void;

    public function delete(int $id): void;

    public function transaction(callable $operation): mixed;
}
