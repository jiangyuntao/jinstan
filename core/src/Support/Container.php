<?php

declare(strict_types=1);

namespace Jinstan\Core\Support;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class Container implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $entries = [];

    public function set(string $id, mixed $entry): void
    {
        $this->entries[$id] = $entry;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new ServiceNotFound($id);
        }

        $entry = $this->entries[$id];

        return $entry instanceof \Closure ? $entry($this) : $entry;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class ServiceNotFound extends \RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('No service is registered for "%s".', $id));
    }
}
