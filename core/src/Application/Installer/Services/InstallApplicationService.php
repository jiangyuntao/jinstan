<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Installer\Services;

use Jinstan\Core\Application\Installer\DatabaseConfigGateway;
use Jinstan\Core\Application\Installer\InstallationDatabaseGateway;
use Jinstan\Core\Application\Installer\InstallationInput;
use Jinstan\Core\Application\Installer\InstallationLockGateway;

final class InstallApplicationService
{
    public function __construct(
        private readonly InstallationDatabaseGateway $database,
        private readonly InstallationLockGateway $lock,
        private readonly InstallationInput $input,
        private readonly DatabaseConfigGateway $configWriter,
    ) {
    }

    /** @param array<string, string> $input */
    public function execute(array $input): void
    {
        if ($this->lock->exists()) {
            throw new \RuntimeException('安装已完成。');
        }

        $installation = $this->input->validate($input);
        $this->database->install(
            $installation['database'],
            $installation['siteName'],
            $installation['email'],
            $installation['password'],
        );
        $this->configWriter->write($installation['database']);
        $this->lock->create();
    }
}
