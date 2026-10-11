<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Requests\Auth;

use Jinstan\Core\Http\Validation\ActionRequest;
use Jinstan\Core\Http\Validation\ValidationResult;

final class LoginRequest extends ActionRequest
{
    public function validate(array $input): ValidationResult
    {
        $email = $this->string($input, 'email');
        $password = $this->string($input, 'password', false);
        $errors = [];

        if ($email === '') {
            $errors['email'][] = '请输入邮箱。';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = '邮箱格式无效。';
        }
        if ($password === '') {
            $errors['password'][] = '请输入密码。';
        } elseif (mb_strlen($password) > 1024) {
            $errors['password'][] = '密码长度不能超过 1024 个字符。';
        }

        return new ValidationResult([
            'email' => $email,
            'password' => $password,
        ], $errors);
    }
}
