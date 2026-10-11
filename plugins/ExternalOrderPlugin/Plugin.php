<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

require_once __DIR__ . '/ExternalOrderFormComponent.php';
require_once __DIR__ . '/ExternalOrderService.php';
require_once __DIR__ . '/ExternalOrderRequest.php';
require_once __DIR__ . '/OrderGateway.php';
require_once __DIR__ . '/FakeOrderGateway.php';
require_once __DIR__ . '/HttpOrderGateway.php';
require_once __DIR__ . '/OutboundUrlPolicy.php';
use Jinstan\Core\Plugin\PluginRegistrar;
use Jinstan\Core\Plugin\PluginServiceProvider;

final class Plugin implements PluginServiceProvider
{
    public function install(): void
    {
    }

    public function enable(): void
    {
    }

    public function disable(): void
    {
    }

    public function upgrade(string $fromVersion): void
    {
    }

    public function uninstall(): void
    {
    }
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->components()->register(new ExternalOrderFormComponent($registrar->csrf()));
        $url = trim((string) $registrar->settings()->get('plugin.external-order.submit_url', ''));
        $parameters = json_decode(
            (string) $registrar->settings()->get(
                'plugin.external-order.parameters',
                '{"product_id":"product_id","email":"email"}',
            ),
            true,
        );
        $gateway = $url === ''
            ? new FakeOrderGateway()
            : new HttpOrderGateway($url, is_array($parameters) ? $parameters : []);
        $orders = new ExternalOrderService($registrar->contents(), $gateway);
        $registrar->routes()->load(
            __DIR__ . '/routes/web.php',
            $registrar,
            new ExternalOrderRequest(),
            $orders,
            $registrar->rateLimiter(),
        );
    }

    public function boot(): void
    {
    }
}
