<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

interface ResponseFileReader
{
    public function read(string $path): string;
}
