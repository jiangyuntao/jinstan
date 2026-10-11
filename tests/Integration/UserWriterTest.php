<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Auth\Services\UserWriter;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Infrastructure\Persistence\Auth\RoleRepository;
use Jinstan\Core\Infrastructure\Persistence\Auth\UserRepository;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class UserWriterTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testItCreatesAndUpdatesUsersWithKnownRoles(): void
    {
        [$writer, $users] = $this->writer();

        $writer->save(null, [
            'email' => 'editor@example.test',
            'password' => 'secret1',
            'roles' => ['editor'],
        ]);
        $user = $users->all()[0];
        $writer->save((int) $user['id'], [
            'email' => 'changed@example.test',
            'password' => '',
            'roles' => [],
        ]);

        self::assertSame('changed@example.test', $users->find((int) $user['id'])['email']);
        self::assertSame([], $users->find((int) $user['id'])['roles']);
    }

    public function testItRejectsRemovingTheCurrentAdministratorRole(): void
    {
        [$writer, $users, $auth] = $this->writer();
        $id = $users->create('admin@example.test', 'secret1', ['administrator']);
        self::assertTrue($auth->attempt('admin@example.test', 'secret1'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不能移除自己当前的管理员角色。');

        $writer->save($id, [
            'email' => 'admin@example.test',
            'password' => '',
            'roles' => ['editor'],
        ]);
    }

    public function testItRejectsUnknownRolesBeforeCreatingAUser(): void
    {
        [$writer] = $this->writer();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('用户包含未知角色。');

        $writer->save(null, [
            'email' => 'user@example.test',
            'password' => 'secret1',
            'roles' => ['unknown'],
        ]);
    }

    /** @return array{UserWriter, UserRepository, Authenticator} */
    private function writer(): array
    {
        $_SESSION = [];
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($connection));
        $users = new UserRepository($connection);
        $roles = new RoleRepository($connection);
        $roles->save(null, 'editor', '编辑', []);

        return [new UserWriter($auth, $users, $roles), $users, $auth];
    }
}
