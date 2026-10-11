<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

use Jinstan\Core\Content\ContentReadRepository;

final class ExternalOrderService
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly OrderGateway $gateway,
    ) {
    }
    public function place(int $productId, string $email): string
    {
        $product = $this->contents->find($productId);
        if ($product === null || $product->type !== 'product' || $product->status !== 'published') {
            throw new \InvalidArgumentException('产品不可订购。');
        }
        return $this->gateway->submit($productId, $email);
    }
}
