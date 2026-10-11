<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Update;

final class UpdateWorkspace
{
    public function __construct(private readonly string $updatesPath)
    {
    }
    public function stagingPath(string $version): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '', $version);
        $path = $this->updatesPath . '/staging-' . $safe;
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new \RuntimeException('无法创建更新临时目录。');
        }
        return $path;
    }
}
