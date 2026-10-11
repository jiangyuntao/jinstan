<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class DefaultFieldTypes
{
    public static function register(FieldTypeRegistrar $types): void
    {
        $simple = [
            'text' => TextField::class,
            'sku' => SkuField::class,
            'textarea' => TextareaField::class,
            'number' => NumberField::class,
            'boolean' => BooleanField::class,
            'switch' => SwitchField::class,
            'date' => DateField::class,
            'datetime' => DateTimeField::class,
            'time' => TimeField::class,
            'month' => MonthField::class,
            'week' => WeekField::class,
            'url' => UrlField::class,
            'email' => EmailField::class,
            'phone' => PhoneField::class,
            'color' => ColorField::class,
            'tags' => TagsField::class,
            'json' => JsonField::class,
            'file' => FileField::class,
            'files' => FilesField::class,
            'image' => ImageField::class,
            'images' => ImagesField::class,
        ];
        foreach ($simple as $handle => $class) {
            $types->register($handle, $class, static fn (array $settings): FieldType => new $class());
        }

        $types->register('body', ContentBodyField::class, static fn (array $settings): FieldType => new ContentBodyField((bool) ($settings['allowHtmlDocument'] ?? false)));
        $types->register('range', RangeField::class, static fn (array $settings): FieldType => new RangeField(
            self::rangeValue($settings, 'rangeMin', 0),
            self::rangeValue($settings, 'rangeMax', 100),
            self::rangeValue($settings, 'rangeStep', 1),
        ));
        $types->register('select', SelectField::class, static fn (array $settings): FieldType => new SelectField(self::options($settings)));
        $types->register('searchable_select', SearchableSelectField::class, static fn (array $settings): FieldType => new SearchableSelectField(self::options($settings)));
        $types->register('radio', RadioField::class, static fn (array $settings): FieldType => new RadioField(self::options($settings)));
        $types->register('checkbox_group', CheckboxGroupField::class, static fn (array $settings): FieldType => new CheckboxGroupField(self::options($settings)));
    }

    /** @param array<string, mixed> $settings */
    private static function rangeValue(array $settings, string $name, int|float $default): int|float
    {
        $value = $settings[$name] ?? $default;
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('范围字段的最小值、最大值和步长必须是数字。');
        }
        $number = str_contains((string) $value, '.') ? (float) $value : (int) $value;
        if (($name === 'rangeStep' && $number <= 0)
            || ($name === 'rangeMax' && $number < self::rangeValue($settings, 'rangeMin', 0))) {
            throw new \InvalidArgumentException('范围字段的配置无效。');
        }

        return $number;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    private static function options(array $settings): array
    {
        $source = trim((string) ($settings['optionsText'] ?? ''));
        $lines = $source === '' ? [] : (preg_split('/\R/u', $source) ?: []);
        $options = [];
        foreach ($lines as $line) {
            [$value, $label] = array_pad(explode('|', $line, 2), 2, '');
            $value = trim($value);
            $label = trim($label);
            if ($value === '' || $label === '') {
                throw new \InvalidArgumentException('选项每行必须使用“值 | 显示名称”格式。');
            }
            if (isset($options[$value])) {
                throw new \InvalidArgumentException(sprintf('选项值“%s”重复。', $value));
            }
            $options[$value] = $label;
        }
        if ($options === []) {
            throw new \InvalidArgumentException('选择类字段至少需要一个选项。');
        }

        return $options;
    }
}
