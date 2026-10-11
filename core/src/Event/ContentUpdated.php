<?php

declare(strict_types=1);

namespace Jinstan\Core\Event;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;

final readonly class ContentUpdated
{
    public function __construct(
        public Content $content,
        public ContentTranslation $translation,
    ) {
    }
}
