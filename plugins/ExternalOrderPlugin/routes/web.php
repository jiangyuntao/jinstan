<?php

declare(strict_types=1);

use Jinstan\Core\Plugin\PluginRegistrar;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Plugins\ExternalOrderPlugin\ExternalOrderRequest;
use Jinstan\Plugins\ExternalOrderPlugin\ExternalOrderService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Jinstan\Core\Security\RateLimiter;

return static function (
    RouteRegistrar $routes,
    PluginRegistrar $registrar,
    ExternalOrderRequest $orderRequest,
    ExternalOrderService $orders,
    RateLimiter $rateLimiter,
): void {
    $routes->post('/external-order', function (ServerRequestInterface $request) use ($registrar, $orderRequest, $orders, $rateLimiter): ResponseInterface {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        if (!$registrar->csrf()->valid(isset($input['_token']) ? (string) $input['_token'] : null)) {
            return $registrar->responses()->text('CSRF token is invalid.', 419);
        }
        $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $address = is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false
            ? $address
            : 'unknown';
        $limit = $rateLimiter->consume('external-order:ip:' . $address, 10, 60);
        if (!$limit->allowed) {
            return $registrar->responses()->text('请求过于频繁，请稍后重试。', 429, [
                'Retry-After' => (string) $limit->retryAfter,
            ]);
        }

        try {
            $validated = $orderRequest->validated($request);
            $reference = $orders->place($validated['product_id'], $validated['email']);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return $registrar->responses()->text($exception->getMessage(), 422);
        }

        return $registrar->responses()->html(
            '<p>订单已提交，参考号：' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</p>',
        );
    });
};
