<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

interface PublicMediaAccessPolicy
{
    public function allows(string $path): bool;
}
