<?php

declare(strict_types=1);

namespace Jinstan\Core\Component;

interface Component
{
    public function name(): string;

    /** @param array<string, mixed> $parameters
     *  @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public function data(array $parameters, array $context): array;
}
