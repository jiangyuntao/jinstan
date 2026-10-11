<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

interface FieldType
{
    public function normalize(mixed $value): mixed;

    public function valid(mixed $value): bool;

    /**
     * 后台字段模板相对于 Admin views 目录的路径。
     */
    public function adminView(): string;

    /** @return array<string, mixed> */
    public function adminData(): array;

    public function adminValue(mixed $value): mixed;
}
