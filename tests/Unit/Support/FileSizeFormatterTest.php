<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Support;

use Jinstan\Core\Support\FileSizeFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileSizeFormatterTest extends TestCase
{
    #[DataProvider('sizes')]
    public function testItFormatsBytesWithReadableUnits(int $bytes, string $expected): void
    {
        self::assertSame($expected, (new FileSizeFormatter())->format($bytes));
    }

    /** @return iterable<string, array{int, string}> */
    public static function sizes(): iterable
    {
        yield 'zero' => [0, '0 B'];
        yield 'bytes' => [1023, '1023 B'];
        yield 'kilobyte' => [1024, '1 KB'];
        yield 'decimal kilobytes' => [1536, '1.5 KB'];
        yield 'megabytes' => [5 * 1024 * 1024, '5 MB'];
        yield 'gigabytes' => [3 * 1024 * 1024 * 1024, '3 GB'];
    }
}
