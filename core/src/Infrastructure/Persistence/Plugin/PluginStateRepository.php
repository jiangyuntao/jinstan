<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Plugin;

use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Plugin\PluginStateRepositoryContract;

final class PluginStateRepository implements PluginStateRepositoryContract
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function version(string $name): ?string
    {
        $version = $this->connection->fetchOne('SELECT version FROM plugins WHERE name = ?', [$name]);

        return $version === false ? null : $version;
    }

    public function enabled(string $name): bool
    {
        return (bool) $this->connection->fetchOne('SELECT enabled FROM plugins WHERE name = ?', [$name]);
    }

    public function save(string $name, string $version, bool $enabled): void
    {
        if ($this->version($name) === null) {
            $this->connection->insert('plugins', ['name' => $name, 'version' => $version, 'enabled' => $enabled ? 1 : 0]);

            return;
        }

        $this->connection->update('plugins', ['version' => $version, 'enabled' => $enabled ? 1 : 0], ['name' => $name]);
    }
}
