<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Storage\Content;

use Jinstan\Core\Content\ContentAttachmentPath;
use Jinstan\Core\Content\ContentAttachmentStore;

final class ContentAttachmentStorage implements ContentAttachmentStore
{
    private const MAX_SIZE = 10_485_760;

    /** @var array<string, list<string>> */
    private const ALLOWED_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    public function __construct(private readonly string $directory)
    {
    }

    public function store(string $source, string $originalName, bool $imagesOnly): string
    {
        if (!is_file($source) || filesize($source) > self::MAX_SIZE) {
            throw new \InvalidArgumentException('上传文件无效或超过 10 MB。');
        }

        $mimeType = mime_content_type($source) ?: '';
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$mimeType])
            || !in_array($extension, self::ALLOWED_TYPES[$mimeType], true)
            || ($imagesOnly && !str_starts_with($mimeType, 'image/'))) {
            throw new \InvalidArgumentException('文件类型不受支持。');
        }

        $hash = md5_file($source);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('无法计算文件哈希。');
        }

        $path = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.' . $extension;
        $target = $this->directory . '/' . $path;
        $targetDirectory = dirname($target);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0750, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('无法创建附件目录。');
        }
        if (!is_file($target) && !copy($source, $target)) {
            throw new \RuntimeException('无法保存附件文件。');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        if (!self::isStoredPath($path)) {
            return;
        }

        $file = $this->directory . '/' . $path;
        if (is_file($file) && !unlink($file)) {
            throw new \RuntimeException('无法删除附件文件。');
        }
        $this->removeEmptyDirectories(dirname($file));
    }

    /** @return array{body: string, mime: string}|null */
    public function read(string $path): ?array
    {
        if (!self::isStoredPath($path)) {
            return null;
        }
        $file = $this->directory . '/' . $path;
        if (!is_file($file)) {
            return null;
        }
        $body = file_get_contents($file);
        if (!is_string($body)) {
            return null;
        }

        return ['body' => $body, 'mime' => mime_content_type($file) ?: 'application/octet-stream'];
    }

    public static function isStoredPath(mixed $value): bool
    {
        return ContentAttachmentPath::isValid($value);
    }

    private function removeEmptyDirectories(string $directory): void
    {
        $root = rtrim($this->directory, DIRECTORY_SEPARATOR);
        while ($directory !== $root && str_starts_with($directory, $root . DIRECTORY_SEPARATOR) && is_dir($directory)) {
            if (scandir($directory) !== ['.', '..']) {
                return;
            }
            rmdir($directory);
            $directory = dirname($directory);
        }
    }
}
