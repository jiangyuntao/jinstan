<?php

declare(strict_types=1);

namespace Jinstan\Core\Event;

use Jinstan\Core\Content\Content;

final readonly class ContentDeleted
{
    public function __construct(public Content $content)
    {
    }
}
