<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Plugin;

use Jinstan\Plugins\ExternalOrderPlugin\OutboundUrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/plugins/ExternalOrderPlugin/OutboundUrlPolicy.php';

final class OutboundUrlPolicyTest extends TestCase
{
    public function testItAcceptsAResolvedPublicHttpsEndpoint(): void
    {
        $policy = new OutboundUrlPolicy(static fn (string $host): array => ['93.184.216.34']);

        $target = $policy->validate('https://orders.example.test/submit');

        self::assertSame('orders.example.test', $target['host']);
        self::assertSame(443, $target['port']);
        self::assertSame(['93.184.216.34'], $target['addresses']);
    }

    #[DataProvider('unsafeUrls')]
    public function testItRejectsUnsafeDestinations(string $url, array $addresses): void
    {
        $policy = new OutboundUrlPolicy(static fn (string $host): array => $addresses);

        $this->expectException(\InvalidArgumentException::class);
        $policy->validate($url);
    }

    public static function unsafeUrls(): iterable
    {
        yield 'localhost' => ['http://localhost/order', ['127.0.0.1']];
        yield 'private ipv4' => ['https://orders.example.test/order', ['10.0.0.2']];
        yield 'link local' => ['https://orders.example.test/order', ['169.254.169.254']];
        yield 'ipv6 loopback' => ['https://[::1]/order', ['::1']];
        yield 'credentials' => ['https://user:secret@orders.example.test/order', ['93.184.216.34']];
        yield 'unsafe port' => ['https://orders.example.test:8080/order', ['93.184.216.34']];
    }
}
