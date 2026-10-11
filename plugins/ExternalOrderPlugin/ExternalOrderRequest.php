<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

use Psr\Http\Message\ServerRequestInterface;

final class ExternalOrderRequest
{
    /** @return array{product_id: int, email: string} */
    public function validated(ServerRequestInterface $request): array
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $email = is_string($input['email'] ?? null) ? trim($input['email']) : '';

        if ($productId === false) {
            throw new \InvalidArgumentException('产品参数无效。');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('请输入有效邮箱。');
        }

        return ['product_id' => $productId, 'email' => $email];
    }
}
