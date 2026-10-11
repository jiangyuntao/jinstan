<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

use Jinstan\Core\Support\Container;

/**
 * 只写的 Plugin Service 注册入口，不向扩展暴露 Container 查询能力。
 */
final readonly class PluginServiceRegistrar
{
    public function __construct(private Container $container)
    {
    }

    public function register(object $service, string ...$contracts): void
    {
        $serviceClass = $service::class;
        $reflection = new \ReflectionClass($service);
        if ($reflection->isAnonymous() || str_starts_with($serviceClass, 'Jinstan\\Core\\')) {
            throw new \InvalidArgumentException('Plugin Service 必须是 Plugin 自有的命名类。');
        }

        $identifiers = [$serviceClass, ...$contracts];
        foreach ($identifiers as $identifier) {
            if ($identifier === '' || (!class_exists($identifier) && !interface_exists($identifier))) {
                throw new \InvalidArgumentException(sprintf('Plugin Service 契约不存在：%s。', $identifier));
            }
            if (!$service instanceof $identifier) {
                throw new \InvalidArgumentException(sprintf('%s 未实现 Service 契约 %s。', $serviceClass, $identifier));
            }
            if ($this->container->has($identifier)) {
                throw new \RuntimeException(sprintf('Service 已注册：%s。', $identifier));
            }
        }

        foreach (array_unique($identifiers) as $identifier) {
            $this->container->set($identifier, $service);
        }
    }
}
