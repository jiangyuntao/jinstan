<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Auth;

use Jinstan\Core\Http\Requests\Admin\UserRequest;
use Jinstan\Core\Http\Exceptions\ValidationException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class UserRequestTest extends TestCase
{
    public function testCreateRequiresPasswordAndNormalizesRoles(): void
    {
        $request = (new ServerRequest('POST', '/admin/users/new'))->withParsedBody([
            'email' => ' editor@example.test ',
            'password' => '',
            'roles' => ['editor', 'editor'],
        ]);
        $result = (new UserRequest())->createResult($request);

        self::assertSame([
            'email' => 'editor@example.test',
            'password' => '',
            'roles' => ['editor'],
        ], $result->normalized());
        $this->assertInvalidFields($result, ['password']);

        $valid = (new UserRequest())->createResult(
            (new ServerRequest('POST', '/admin/users/new'))->withParsedBody([
                'email' => 'editor@example.test',
                'password' => '123456',
                'roles' => [],
            ]),
        );
        self::assertTrue($valid->isValid());
    }

    public function testUpdateAllowsBlankPasswordAndRejectsMalformedRoles(): void
    {
        $valid = (new UserRequest())->validate([
            'email' => 'editor@example.test',
            'password' => '',
            'roles' => ['editor'],
        ]);
        self::assertTrue($valid->isValid());

        $invalid = (new UserRequest())->validate([
            'email' => 'invalid',
            'password' => 'short',
            'roles' => [['editor']],
        ]);
        $this->assertInvalidFields($invalid, ['email', 'password', 'roles']);
    }

    public function testProfileDoesNotAcceptRolesAndKeepsExistingTrimBehavior(): void
    {
        $request = (new ServerRequest('POST', '/admin/profile'))->withParsedBody([
            'email' => ' admin@example.test ',
            'password' => '        ',
            'roles' => ['administrator'],
        ]);

        self::assertSame([
            'email' => 'admin@example.test',
            'password' => '',
            'roles' => [],
        ], (new UserRequest())->profileResult($request)->validated());
    }

    /** @param list<string> $fields */
    private function assertInvalidFields(\Jinstan\Core\Http\Validation\ValidationResult $result, array $fields): void
    {
        try {
            $result->validated();
            self::fail('Expected validation to fail.');
        } catch (ValidationException $exception) {
            self::assertSame($fields, array_keys($exception->errors()));
        }
    }
}
