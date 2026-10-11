<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

use Jinstan\Core\Component\Component;
use Jinstan\Core\Security\CsrfTokenProvider;

final class ExternalOrderFormComponent implements Component
{
    public function __construct(private readonly CsrfTokenProvider $csrf)
    {
    }
    public function name(): string
    {
        return 'external-order-form';
    }
    public function data(array $parameters, array $context): array
    {
        return ['product_id' => (int) ($parameters['product_id'] ?? 0), 'csrf_token' => $this->csrf->value()];
    }
}
