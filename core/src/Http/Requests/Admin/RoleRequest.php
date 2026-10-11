<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Requests\Admin;

use Jinstan\Core\Http\Validation\ActionRequest;
use Jinstan\Core\Http\Validation\ValidationResult;

final class RoleRequest extends ActionRequest
{
    public function validate(array $input): ValidationResult
    {
        $key = $this->string($input, 'role_key');
        $label = $this->string($input, 'label');
        $permissions = $this->stringList($input, 'permissions');
        $errors = [];

        if (preg_match('/^[a-z][a-z0-9._-]{1,99}$/', $key) !== 1) {
            $errors['role_key'][] = '角色标识必须以小写字母开头，且只能包含小写字母、数字、点、连字符和下划线。';
        }
        if ($label === '') {
            $errors['label'][] = '请输入角色名称。';
        }
        if ($permissions === null) {
            $errors['permissions'][] = '角色权限格式无效。';
            $permissions = [];
        }

        return new ValidationResult([
            'role_key' => $key,
            'label' => $label,
            'permissions' => $permissions,
        ], $errors);
    }
}
