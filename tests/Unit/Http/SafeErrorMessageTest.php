<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\SafeErrorMessage;
use PDOException;
use PHPUnit\Framework\TestCase;

final class SafeErrorMessageTest extends TestCase
{
    public function testItKeepsValidationMessages(): void
    {
        self::assertSame(
            '邮箱格式无效。',
            SafeErrorMessage::from(new \InvalidArgumentException('邮箱格式无效。'), '保存失败。'),
        );
    }

    public function testItHidesInfrastructureErrors(): void
    {
        self::assertSame(
            '保存失败。',
            SafeErrorMessage::from(new PDOException('SQLSTATE password=/secret/path'), '保存失败。'),
        );
    }
}
