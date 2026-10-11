<?php

declare(strict_types=1);

namespace Jinstan\Core\Component;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;

final class BreadcrumbComponent implements Component
{
    public function __construct(private readonly ?ContentTypeRegistry $types = null)
    {
    }

    public function name(): string
    {
        return 'breadcrumb';
    }

    public function data(array $parameters, array $context): array
    {
        $translation = $context['translation'] ?? null;
        $content = $context['content'] ?? null;
        $items = [['label' => 'Home', 'url' => '/']];
        if ($content instanceof Content && $this->types !== null) {
            $type = $this->types->get($content->type);
            if ($type->listEnabled && $type->listPath !== '') {
                $items[] = ['label' => $type->label, 'url' => $type->listPath];
            }
        }
        if ($translation instanceof ContentTranslation) {
            $items[] = ['label' => $translation->title, 'url' => null];
        }

        return ['items' => $items];
    }
}
