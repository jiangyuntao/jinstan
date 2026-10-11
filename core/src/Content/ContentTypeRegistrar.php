<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

interface ContentTypeRegistrar
{
    public function register(ContentTypeDefinition $definition): void;
}
