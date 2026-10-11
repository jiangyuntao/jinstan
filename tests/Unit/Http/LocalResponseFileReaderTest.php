<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Infrastructure\Filesystem\Http\LocalResponseFileReader;
use PHPUnit\Framework\TestCase;

final class LocalResponseFileReaderTest extends TestCase
{
    public function testItRejectsAnUnreadableResponseFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Response file is not readable.');

        (new LocalResponseFileReader())->read(sys_get_temp_dir() . '/jinstan-missing-response-file');
    }
}
