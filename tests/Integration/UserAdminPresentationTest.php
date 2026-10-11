<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\UserAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\UserAdminQuery;
use Jinstan\Core\Infrastructure\Persistence\Auth\RoleRepository;
use Jinstan\Core\Infrastructure\Persistence\Auth\UserRepository;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class UserAdminPresentationTest extends TestCase
{
    public function testItBuildsTheUserListAndFormViewModels(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $users = new UserRepository($connection);
        $roles = new RoleRepository($connection);
        $users->create('initial@example.test', 'secret1', ['administrator']);
        $users->create('author@example.test', 'secret2', []);
        $query = new UserAdminQuery($users, $roles);
        $presenter = new UserAdminPresenter(
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                sys_get_temp_dir() . '/jinstan-user-presenter',
                TestUrlGenerator::adminViews(),
            ),
            new CsrfToken(),
        );

        $index = $presenter->index($query->users());
        $form = $presenter->form($query->user(2), $query->roles(), '测试错误');

        self::assertSame('users', $index->template);
        self::assertFalse($index->data['users'][0]['deletable']);
        self::assertTrue($index->data['users'][1]['deletable']);
        self::assertSame('actions', $index->data['columns'][4]['name']);
        self::assertSame('user-form', $form->template);
        self::assertSame('编辑用户', $form->title);
        self::assertSame('author@example.test', $form->data['user']['email']);
        self::assertSame('测试错误', $form->data['error']);
        self::assertSame('administrator', $form->data['roles'][0]['role_key']);
    }

    public function testUnknownUserRaisesANotFoundSignal(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $query = new UserAdminQuery(new UserRepository($connection), new RoleRepository($connection));

        $this->expectException(\OutOfBoundsException::class);

        $query->user(999);
    }
}
