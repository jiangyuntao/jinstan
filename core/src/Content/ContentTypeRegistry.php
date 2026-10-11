<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

use InvalidArgumentException;

final class ContentTypeRegistry implements ContentTypeRegistrar
{
    /** @var array<string, ContentTypeDefinition> */
    private array $types = [];

    public function register(ContentTypeDefinition $definition): void
    {
        $this->types[$definition->handle] = $definition;
    }

    public function get(string $handle): ContentTypeDefinition
    {
        return $this->types[$handle] ?? throw new InvalidArgumentException(sprintf('未知内容类型：%s。', $handle));
    }

    /** @return array<string, ContentTypeDefinition> */
    public function all(): array
    {
        return $this->types;
    }
}
