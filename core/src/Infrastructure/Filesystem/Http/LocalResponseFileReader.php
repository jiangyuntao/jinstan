<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Http;

use Jinstan\Core\Http\ResponseFileReader;

final class LocalResponseFileReader implements ResponseFileReader
{
    public function read(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Response file is not readable.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Response file could not be read.');
        }

        return $content;
    }
}
