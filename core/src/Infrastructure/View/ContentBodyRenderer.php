<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\View\ContentBodyView;
use League\CommonMark\CommonMarkConverter;

final class ContentBodyRenderer implements ContentBodyView
{
    public function render(mixed $value): string
    {
        if (is_array($value)) {
            if (($value['format'] ?? '') === 'html_document') {
                return (string) ($value['value'] ?? '');
            }

            $value = (string) ($value['value'] ?? '');
        }

        return (new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]))->convert($value)->getContent();
    }
}
