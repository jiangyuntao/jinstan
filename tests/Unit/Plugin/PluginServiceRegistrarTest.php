<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Plugin;

use Jinstan\Core\Plugin\PluginServiceRegistrar;
use Jinstan\Core\Support\Container;
use PHPUnit\Framework\TestCase;

final class PluginServiceRegistrarTest extends TestCase
{
    public function testItRegistersAPluginServiceByClassAndContract(): void
    {
        $container = new Container();
        $registrar = new PluginServiceRegistrar($container);
        $service = new ExamplePluginService();

        $registrar->register($service, ExamplePluginContract::class);

        self::assertSame($service, $container->get(ExamplePluginService::class));
        self::assertSame($service, $container->get(ExamplePluginContract::class));
    }

    public function testItCannotReplaceAnExistingService(): void
    {
        $container = new Container();
        $container->set(ExamplePluginContract::class, new ExamplePluginService());
        $registrar = new PluginServiceRegistrar($container);

        $this->expectException(\RuntimeException::class);
        $registrar->register(new ExamplePluginService(), ExamplePluginContract::class);
    }

    public function testItRejectsAnUnimplementedContractWithoutPartialRegistration(): void
    {
        $container = new Container();
        $registrar = new PluginServiceRegistrar($container);

        try {
            $registrar->register(new ExamplePluginService(), \Countable::class);
            self::fail('Expected an incompatible service contract to be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertFalse($container->has(ExamplePluginService::class));
        }
    }
}

interface ExamplePluginContract
{
}

final class ExamplePluginService implements ExamplePluginContract
{
}
