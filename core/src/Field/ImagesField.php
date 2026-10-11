<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class ImagesField extends FilesField
{
    public function adminView(): string
    {
        return 'fields/images.jst';
    }
    public function imagesOnly(): bool
    {
        return true;
    }
}
