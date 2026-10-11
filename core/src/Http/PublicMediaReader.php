<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

interface PublicMediaReader
{
    /** @param array<string, string> $routeParams
     *  @return array{body: string, mime: string}|null
     */
    public function read(array $routeParams): ?array;
}
