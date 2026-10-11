<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class FieldTypeRegistry implements FieldTypeRegistrar
{
    /** @var array<string, array{class: class-string<FieldType>, factory: \Closure(array<string, mixed>): FieldType}> */
    private array $types = [];

    public function register(string $handle, string $fieldClass, callable $factory): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,48}$/D', $handle) !== 1) {
            throw new \InvalidArgumentException('Field Type handle 必须由小写字母、数字或下划线组成。');
        }
        if (!is_a($fieldClass, FieldType::class, true)) {
            throw new \InvalidArgumentException(sprintf('%s 未实现 FieldType。', $fieldClass));
        }
        if (isset($this->types[$handle])) {
            throw new \RuntimeException(sprintf('Field Type 已注册：%s。', $handle));
        }
        foreach ($this->types as $registered) {
            if ($registered['class'] === $fieldClass) {
                throw new \RuntimeException(sprintf('Field Type 类已注册：%s。', $fieldClass));
            }
        }

        $this->types[$handle] = [
            'class' => $fieldClass,
            'factory' => \Closure::fromCallable($factory),
        ];
    }

    /** @param array<string, mixed> $settings */
    public function create(string $handle, array $settings = []): FieldType
    {
        $definition = $this->types[$handle] ?? throw new \InvalidArgumentException(
            sprintf('不支持的字段类型：%s。', $handle),
        );
        $field = ($definition['factory'])($settings);
        if (!$field instanceof $definition['class']) {
            throw new \RuntimeException(sprintf('Field Type %s 的 factory 返回了错误类型。', $handle));
        }

        return $field;
    }

    public function handle(FieldType $field): string
    {
        foreach ($this->types as $handle => $definition) {
            if ($field::class === $definition['class']) {
                return $handle;
            }
        }

        throw new \InvalidArgumentException('Field Type 未注册，无法持久化。');
    }
}
