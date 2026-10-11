<?php

declare(strict_types=1);

namespace Jinstan\Core\SEO;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;

interface SchemaRegistrar
{
    /** @param callable(Content, ContentTranslation): array<string, mixed> $provider */
    public function add(callable $provider): void;
}
