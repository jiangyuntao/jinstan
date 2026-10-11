<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Services;

use Jinstan\Core\Application\Content\ContentOperationDenied;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Settings\SettingsReadRepository;
use Psr\Http\Message\UploadedFileInterface;

final class CreateContent
{
    public function __construct(
        private readonly ContentService $contents,
        private readonly ContentAttachmentCoordinator $attachments,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /** @param array<string, UploadedFileInterface|array<mixed>> $uploadedFiles
     *  @param array<string, mixed> $input
     */
    public function execute(array $uploadedFiles, ContentTypeDefinition $type, array $input, string $locale, ?int $authorId, bool $canUseHtmlDocument): void
    {
        $created = [];
        try {
            [$data] = $this->attachments->prepare($uploadedFiles, $type, $input, $created);
            foreach ($data as $value) {
                if (is_array($value) && ($value['format'] ?? null) === 'html_document' && !$canUseHtmlDocument) {
                    throw new ContentOperationDenied('完整 HTML 页面需要系统管理权限。');
                }
            }
            $parentId = $type->hierarchical ? (int) ($input['parent_id'] ?? 0) : 0;
            $published = array_key_exists('published', $input)
                ? filter_var($input['published'], FILTER_VALIDATE_BOOL)
                : $this->settings->get('content.default_status', 'draft') === 'published';
            $this->contents->saveWithTerms(
                $type->handle,
                null,
                new ContentTranslation($locale, $input['title'], $input['slug'], null, $data),
                $data,
                $input['terms'],
                $authorId,
                $parentId > 0 ? $parentId : null,
                $published,
                isset($input['template']) && is_string($input['template']) ? $input['template'] : null,
            );
        } catch (\Throwable $exception) {
            $this->attachments->deleteUnreferenced($created);
            throw $exception;
        }
    }
}
