<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

final class ContentAttachmentPath
{
    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('#^[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{32}\.(?:jpe?g|png|webp|pdf)$#', $value) === 1;
    }
}
