<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Auth;

use Jinstan\Core\Http\Requests\Admin\RoleRequest;
use Jinstan\Core\Http\Exceptions\ValidationException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RoleRequestTest extends TestCase
{
    public function testItNormalizesRoleFieldsAndDeduplicatesPermissions(): void
    {
        $request = (new ServerRequest('POST', '/admin/roles/new'))->withParsedBody([
            'role_key' => ' editor ',
            'label' => ' 内容编辑 ',
            'permissions' => ['content.manage', 'content.manage', 'settings.manage'],
        ]);

        self::assertSame([
            'role_key' => 'editor',
            'label' => '内容编辑',
            'permissions' => ['content.manage', 'settings.manage'],
        ], (new RoleRequest())->validated($request));
    }

    public function testItRejectsInvalidFieldsWithoutCoercingNestedValues(): void
    {
        $result = (new RoleRequest())->validate([
            'role_key' => 'Editor',
            'label' => [],
            'permissions' => ['content.manage', ['system.manage']],
        ]);

        self::assertSame([
            'role_key' => 'Editor',
            'label' => '',
            'permissions' => [],
        ], $result->normalized());

        try {
            $result->validated();
            self::fail('Expected validation to fail.');
        } catch (ValidationException $exception) {
            self::assertSame(['role_key', 'label', 'permissions'], array_keys($exception->errors()));
        }
    }
}
