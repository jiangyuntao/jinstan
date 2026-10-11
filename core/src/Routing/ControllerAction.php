<?php

declare(strict_types=1);

namespace Jinstan\Core\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ControllerAction
{
    /** @param array{class-string, non-empty-string} $action */
    public function __construct(
        private readonly array $action,
        private readonly ?ContainerInterface $container,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function __invoke(ServerRequestInterface $request, array $parameters): ResponseInterface
    {
        [$controller, $method] = $this->action;
        if ($this->container === null || !$this->container->has($controller)) {
            throw new \RuntimeException(sprintf('Route controller is not registered: %s', $controller));
        }

        $instance = $this->container->get($controller);
        if (!is_object($instance) || !is_callable([$instance, $method])) {
            throw new \RuntimeException(sprintf('Route controller action is not callable: %s::%s', $controller, $method));
        }

        return $instance->{$method}($request, $parameters);
    }
}
