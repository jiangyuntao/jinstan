<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

use Jinstan\Core\Field\FieldDefinition;

final class ContentTypeDefinition
{
    /** @param array<string, FieldDefinition> $fields */
    public function __construct(
        public readonly string $handle,
        public readonly string $label,
        public readonly array $fields,
        public readonly string $defaultTemplate,
        public readonly string $urlPattern,
        public readonly bool $hierarchical = false,
        /** @var array<string, array{multiple: bool, required: bool}> */
        public readonly array $taxonomies = [],
        public readonly bool $listEnabled = false,
        public readonly string $listPath = '',
        public readonly string $listTemplate = 'content-list',
        public readonly int $perPage = 12,
    ) {
    }
}
