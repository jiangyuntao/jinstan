<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\RoleAdminPresenter;
use Jinstan\Core\Application\Auth\Queries\RoleAdminQuery;
use Jinstan\Core\Application\Auth\Services\RoleWriter;
use Jinstan\Core\Infrastructure\Persistence\Auth\RoleRepository;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class RoleAdminApplicationTest extends TestCase
{
    public function testItCoordinatesRoleWritesQueriesAndPresentation(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $roles = new RoleRepository($connection);
        $writer = new RoleWriter($roles);
        $query = new RoleAdminQuery($roles);
        $presenter = new RoleAdminPresenter(
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                sys_get_temp_dir() . '/jinstan-role-presenter',
                TestUrlGenerator::adminViews(),
            ),
            new CsrfToken(),
        );

        $writer->save(null, [
            'role_key' => 'auditor',
            'label' => '审计员',
            'permissions' => ['content.manage'],
        ]);
        $role = $query->role('auditor');
        $form = $presenter->form($role, $query->permissions(), '测试错误');
        $index = $presenter->index($query->roles());

        self::assertSame(['content.manage'], $role['permissions']);
        self::assertSame('role-form', $form->template);
        self::assertSame('编辑角色', $form->title);
        self::assertSame('测试错误', $form->data['error']);
        self::assertSame('roles', $index->template);
        self::assertSame('auditor', $index->data['roles'][1]['role_key']);

        $writer->delete('auditor');
        $this->expectException(\OutOfBoundsException::class);
        $query->role('auditor');
    }

    public function testUnknownRoleRaisesANotFoundSignal(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');

        $this->expectException(\OutOfBoundsException::class);

        (new RoleAdminQuery(new RoleRepository($connection)))->role('missing');
    }
}
