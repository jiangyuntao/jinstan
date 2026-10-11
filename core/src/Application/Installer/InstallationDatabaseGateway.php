<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Installer;

interface InstallationDatabaseGateway
{
    /** @param array<string, mixed> $database */
    public function install(array $database, string $siteName, string $email, string $password): void;
}
