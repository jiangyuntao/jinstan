<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Logging;

use Jinstan\Core\Infrastructure\Logging\LoggerFactory;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerFactoryTest extends TestCase
{
    public function testItCreatesAStreamLoggerForTheRequestedChannel(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-logger-' . bin2hex(random_bytes(6)) . '.log';

        try {
            $logger = (new LoggerFactory())->create($path, 'installer');
            $logger->info('ready');

            self::assertInstanceOf(Logger::class, $logger);
            self::assertSame('installer', $logger->getName());
            self::assertStringContainsString('ready', (string) file_get_contents($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
