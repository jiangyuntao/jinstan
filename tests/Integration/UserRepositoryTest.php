<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Persistence\Auth\UserRepository;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private Connection $database;
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->database = (new ConnectionFactory())->create([
            'driver' => 'sqlite',
            'path' => ':memory:',
        ]);
        (new MigrationRunner())->migrate($this->database, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $this->users = new UserRepository($this->database);
    }

    public function testNewUserReceivesOnlyExplicitRoles(): void
    {
        $id = $this->users->create('new-user@example.test', 'password1', ['administrator']);

        self::assertSame(
            'administrator',
            $this->database->fetchOne('SELECT role_key FROM user_roles WHERE user_id = ?', [$id]),
        );
    }

    public function testInitialAdministratorCannotBeDeleted(): void
    {
        $id = $this->users->create('initial@example.test', 'password1', ['administrator']);
        self::assertSame(1, $id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('初始管理员不可删除。');

        $this->users->delete($id);
    }
}
