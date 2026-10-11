<?php

declare(strict_types=1);

namespace Jinstan\Core\I18n;

interface TranslationRegistrar
{
    public function register(string $namespace, string $catalogPath): void;
}
