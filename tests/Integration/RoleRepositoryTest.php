<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Persistence\Auth\RoleRepository;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Infrastructure\Persistence\Auth\UserRepository;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class RoleRepositoryTest extends TestCase
{
    public function testItCreatesRolesAssignsPermissionsAndAssignsUsers(): void
    {
        $database = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($database, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $roles = new RoleRepository($database);
        $roles->save(null, 'editor', '编辑', ['content.manage']);

        $userId = (new UserRepository($database))->create('editor@example.test', 'password1', ['editor']);
        $_SESSION['_jinstan_user_id'] = $userId;

        self::assertSame(['content.manage'], $roles->find('editor')['permissions']);
        self::assertSame('editor', $database->fetchOne('SELECT role_key FROM user_roles WHERE user_id = ?', [$userId]));
        self::assertTrue((new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($database)))->can('content.manage'));
        self::assertFalse((new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($database)))->can('system.manage'));
        unset($_SESSION['_jinstan_user_id']);
    }

    public function testAdministratorRoleCannotBeDeletedOrLosePermissions(): void
    {
        $database = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($database, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $roles = new RoleRepository($database);
        $roles->save('administrator', 'administrator', '管理员', []);

        self::assertCount(4, $roles->find('administrator')['permissions']);

        $this->expectException(\InvalidArgumentException::class);
        $roles->delete('administrator');
    }

    public function testLastAdministratorRoleCannotBeRemovedFromUser(): void
    {
        $database = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($database, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $users = new UserRepository($database);
        $id = $users->create('admin@example.test', 'password1', ['administrator']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('系统必须至少保留一名管理员。');

        $users->updateRoles($id, []);
    }

    public function testAdministratorCanUsePermissionBeforeItsCatalogEntryIsMigrated(): void
    {
        $database = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($database, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $id = (new UserRepository($database))->create('admin@example.test', 'password1', ['administrator']);
        $_SESSION['_jinstan_user_id'] = $id;

        self::assertTrue((new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($database)))->can('future-plugin.manage'));

        unset($_SESSION['_jinstan_user_id']);
    }
}
