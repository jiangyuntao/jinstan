<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

final class LoggerFactory
{
    public function create(string $logPath, string $channel = 'jinstan'): Logger
    {
        $logger = new Logger($channel);
        $logger->pushHandler(new StreamHandler($logPath));

        return $logger;
    }
}
