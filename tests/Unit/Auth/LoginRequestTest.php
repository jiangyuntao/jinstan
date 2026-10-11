<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Auth;

use Jinstan\Core\Http\Requests\Auth\LoginRequest;
use Jinstan\Core\Http\Exceptions\ValidationException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class LoginRequestTest extends TestCase
{
    public function testItNormalizesAndReturnsValidatedCredentials(): void
    {
        $request = (new ServerRequest('POST', '/login'))->withParsedBody([
            'email' => '  admin@example.test ',
            'password' => ' password with spaces ',
        ]);

        self::assertSame([
            'email' => 'admin@example.test',
            'password' => ' password with spaces ',
        ], (new LoginRequest())->validated($request));
    }

    public function testItReturnsStableFieldErrorsForInvalidInput(): void
    {
        try {
            (new LoginRequest())->validated(
                (new ServerRequest('POST', '/login'))->withParsedBody([
                    'email' => 'invalid',
                    'password' => [],
                ]),
            );
            self::fail('Expected validation to fail.');
        } catch (ValidationException $exception) {
            self::assertSame('表单验证失败。', $exception->getMessage());
            self::assertSame([
                'email' => ['邮箱格式无效。'],
                'password' => ['请输入密码。'],
            ], $exception->errors());
        }
    }
}
