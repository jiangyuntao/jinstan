<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class ImageField extends FileField
{
    public function adminView(): string
    {
        return 'fields/image.jst';
    }
    public function imagesOnly(): bool
    {
        return true;
    }
}
