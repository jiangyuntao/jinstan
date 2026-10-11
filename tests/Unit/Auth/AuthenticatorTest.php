<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Auth;

use Jinstan\Core\Auth\AuthenticationReadRepository;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AuthenticatorTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItAuthenticatesAndDelegatesPermissionQueriesToTheContract(): void
    {
        $repository = new class () implements AuthenticationReadRepository {
            public function credentials(string $email): ?array
            {
                return $email === 'admin@example.test'
                    ? ['id' => 42, 'passwordHash' => password_hash('secret', PASSWORD_DEFAULT)]
                    : null;
            }

            public function isAdministrator(int $userId): bool
            {
                return false;
            }

            public function hasPermission(int $userId, string $permission): bool
            {
                return $userId === 42 && $permission === 'content.manage';
            }
        };
        $auth = new Authenticator($repository);

        self::assertFalse($auth->attempt('unknown@example.test', 'secret'));
        self::assertFalse($auth->attempt('admin@example.test', 'wrong'));
        self::assertTrue($auth->attempt('admin@example.test', 'secret'));
        $authenticatedSessionId = session_id();
        self::assertSame(42, $auth->userId());
        self::assertTrue($auth->can('content.manage'));
        self::assertFalse($auth->can('system.manage'));

        $auth->logout();
        self::assertNull($auth->userId());
        self::assertNotSame($authenticatedSessionId, session_id());
        session_write_close();
    }
}
