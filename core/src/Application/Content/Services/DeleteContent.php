<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Services;

use Jinstan\Core\Content\ContentRepositoryContract;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Event\ContentDeleted;
use Jinstan\Core\Event\EventDispatcher;

final class DeleteContent
{
    public function __construct(
        private readonly ContentRepositoryContract $contents,
        private readonly ContentTypeRegistry $types,
        private readonly ContentAttachmentCoordinator $attachments,
        private readonly EventDispatcher $events,
    ) {
    }

    public function execute(string $type, int $id): bool
    {
        $content = $this->contents->find($id);
        if ($content === null || $content->type !== $type) {
            return false;
        }

        // 文件删除只在数据库提交后执行，避免回滚后留下仍被引用却已丢失的附件。
        $paths = $this->contents->transaction(function () use ($content): array {
            $paths = $this->attachments->pathsForContent(
                $this->types->get($content->type),
                $content->data,
                $this->contents->translations($content->id),
            );
            $this->contents->delete($content->id);

            return $paths;
        });
        $this->attachments->deleteUnreferenced($paths);
        $this->events->dispatch(new ContentDeleted($content));

        return true;
    }
}
