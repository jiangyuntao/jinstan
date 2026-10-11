<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content;

interface TemplateCatalogReader
{
    /** @return array<string, string> */
    public function options(string $kind): array;

    /** @return array<string, string> */
    public function files(string $kind): array;

    public function path(?string $handle): ?string;

    public function validate(string $handle, string $kind): void;

    public function homepage(): string;
}
