<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Requests\Admin;

use Jinstan\Core\Http\Validation\ActionRequest;
use Jinstan\Core\Http\Validation\ValidationResult;
use Psr\Http\Message\ServerRequestInterface;

final class UserRequest extends ActionRequest
{
    public function validate(array $input): ValidationResult
    {
        return $this->validateInput($input, false, true, false);
    }

    public function createResult(ServerRequestInterface $request): ValidationResult
    {
        return $this->validateInput($this->input($request), true, true, false);
    }

    public function profileResult(ServerRequestInterface $request): ValidationResult
    {
        return $this->validateInput($this->input($request), false, false, true);
    }

    /** @param array<string, mixed> $input */
    private function validateInput(
        array $input,
        bool $passwordRequired,
        bool $includeRoles,
        bool $trimPassword,
    ): ValidationResult {
        $email = $this->string($input, 'email');
        $password = $this->string($input, 'password', $trimPassword);
        $roles = $includeRoles ? $this->stringList($input, 'roles') : [];
        $errors = [];

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = '请输入有效的邮箱地址。';
        }
        if (($passwordRequired || $password !== '') && mb_strlen($password) < 6) {
            $errors['password'][] = '密码至少需要 6 个字符。';
        } elseif (mb_strlen($password) > 1024) {
            $errors['password'][] = '密码长度不能超过 1024 个字符。';
        }
        if ($roles === null) {
            $errors['roles'][] = '用户角色格式无效。';
            $roles = [];
        }

        return new ValidationResult([
            'email' => $email,
            'password' => $password,
            'roles' => $roles,
        ], $errors);
    }
}
