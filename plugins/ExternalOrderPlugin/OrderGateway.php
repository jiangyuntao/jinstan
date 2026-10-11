<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

interface OrderGateway
{
    public function submit(int $productId, string $email): string;
}
