<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

final class FakeOrderGateway implements OrderGateway
{
    public function submit(int $productId, string $email): string
    {
        return 'FAKE-' . $productId . '-' . substr(hash('sha256', $email), 0, 8);
    }
}
