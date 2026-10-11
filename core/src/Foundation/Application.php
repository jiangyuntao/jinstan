<?php

declare(strict_types=1);

namespace Jinstan\Core\Foundation;

use Jinstan\Core\Providers\ServiceProvider;
use Jinstan\Core\Support\Container;
use LogicException;

final class Application
{
    /** @var list<ServiceProvider> */
    private array $providers = [];

    /** @var array<class-string<ServiceProvider>, true> */
    private array $registeredProviders = [];

    private bool $booted = false;

    private readonly Container $container;

    public function __construct(
        private readonly string $projectPath,
        private readonly string $environment = 'production',
        ?Container $container = null,
    ) {
        if ($projectPath === '') {
            throw new LogicException('Project path cannot be empty.');
        }

        if ($environment === '') {
            throw new LogicException('Application environment cannot be empty.');
        }

        $this->container = $container ?? new Container();
        $this->container->set(self::class, $this);
    }

    public function register(ServiceProvider $provider): void
    {
        if ($this->booted) {
            throw new LogicException('Service providers cannot be registered after the application has booted.');
        }

        $class = $provider::class;
        if (isset($this->registeredProviders[$class])) {
            return;
        }

        $provider->register($this);
        $this->providers[] = $provider;
        $this->registeredProviders[$class] = true;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }

        $this->booted = true;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function projectPath(string $path = ''): string
    {
        return $this->projectPath . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function isEnvironment(string ...$environments): bool
    {
        return in_array($this->environment, $environments, true);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
