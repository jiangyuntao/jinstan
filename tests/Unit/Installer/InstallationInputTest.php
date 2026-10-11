<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Installer;

use Jinstan\Core\Application\Installer\InstallationInput;
use PHPUnit\Framework\TestCase;

final class InstallationInputTest extends TestCase
{
    public function testItAcceptsSixCharacterAdminPasswords(): void
    {
        $input = (new InstallationInput('/project'))->validate([
            'site_name' => 'Example',
            'email' => 'admin@example.test',
            'password' => '123456',
            'database_driver' => 'sqlite',
        ]);

        self::assertSame('123456', $input['password']);
        self::assertSame('/project/storage/database/database.sqlite', $input['database']['path']);
    }

    public function testItRejectsShortAdminPasswords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('至少 6 位');

        (new InstallationInput('/project'))->validate([
            'site_name' => 'Example',
            'email' => 'admin@example.test',
            'password' => '12345',
        ]);
    }
}
