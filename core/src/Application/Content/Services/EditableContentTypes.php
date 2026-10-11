<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content\Services;

use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentUrls;
use Jinstan\Core\Field\CheckboxGroupField;
use Jinstan\Core\Field\ContentBodyField;
use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\FieldTypeRegistry;
use Jinstan\Core\Field\FieldType;
use Jinstan\Core\Field\RadioField;
use Jinstan\Core\Field\RangeField;
use Jinstan\Core\Field\SearchableSelectField;
use Jinstan\Core\Field\SelectField;
use Jinstan\Core\Settings\SettingsRepositoryContract;

final class EditableContentTypes
{
    public function __construct(
        private readonly SettingsRepositoryContract $settings,
        private readonly FieldTypeRegistry $fieldTypes,
    ) {
    }

    public function load(ContentTypeRegistry $types): void
    {
        $saved = json_decode($this->settings->get('content.types', '[]') ?? '[]', true);
        if (!is_array($saved)) {
            return;
        }

        foreach ($saved as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            try {
                $types->register($this->definition($definition));
            } catch (\InvalidArgumentException) {
                // 损坏的一项配置不应阻断整个后台和前台。
            }
        }
    }

    /** @param list<array<string, mixed>> $definitions */
    public function save(array $definitions): void
    {
        $this->settings->set('content.types', json_encode($definitions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    public function export(ContentTypeDefinition $definition): array
    {
        $fields = [];
        foreach ($definition->fields as $field) {
            $fields[] = [
                'name' => $field->name,
                'label' => $field->label,
                'type' => $this->fieldTypes->handle($field->type),
                'required' => $field->required,
                'translatable' => $field->translatable,
                ...$this->fieldSettings($field->type),
            ];
        }
        return ['handle' => $definition->handle, 'label' => $definition->label, 'defaultTemplate' => $definition->defaultTemplate, 'urlPattern' => $definition->urlPattern, 'hierarchical' => $definition->hierarchical, 'fields' => $fields, 'taxonomies' => $definition->taxonomies, 'listEnabled' => $definition->listEnabled, 'listPath' => $definition->listPath, 'listTemplate' => $definition->listTemplate, 'perPage' => $definition->perPage];
    }

    /** @param array<string, mixed> $data */
    public function definition(array $data): ContentTypeDefinition
    {
        $handle = (string) ($data['handle'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_]{1,48}$/', $handle)) {
            throw new \InvalidArgumentException('Content Type handle 必须由小写字母、数字或下划线组成。');
        }
        $fields = [];
        $rawFields = $data['fields'] ?? [];
        if (!is_array($rawFields)) {
            throw new \InvalidArgumentException('字段配置无效。');
        }
        $hasProductSku = false;
        if ($handle === 'product') {
            foreach ($rawFields as $field) {
                if (is_array($field) && ($field['name'] ?? null) === 'sku') {
                    $hasProductSku = true;
                    break;
                }
            }
        }
        foreach ($rawFields as $field) {
            if (!is_array($field)) {
                throw new \InvalidArgumentException('字段配置无效。');
            }
            $name = (string) ($field['name'] ?? '');
            if ($hasProductSku && $name === 'price') {
                continue;
            }
            if (!preg_match('/^[a-z][a-z0-9_]{1,48}$/', $name)) {
                throw new \InvalidArgumentException('字段 handle 无效。');
            }
            if (isset($fields[$name])) {
                throw new \InvalidArgumentException(sprintf('字段 handle “%s”重复。', $name));
            }
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                throw new \InvalidArgumentException('字段显示名称不能为空。');
            }
            $fieldType = (string) ($field['type'] ?? '');
            // 旧站点的默认 Product 将 SKU 保存为 text，加载时升级为专用字段。
            if ($handle === 'product' && $name === 'sku' && $fieldType === 'text') {
                $fieldType = 'sku';
            }
            $fields[$name] = new FieldDefinition($name, $label, $this->fieldTypes->create($fieldType, $field), (bool) ($field['required'] ?? false), (bool) ($field['translatable'] ?? false));
        }
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('内容类型名称不能为空。');
        }
        $bindings = [];
        if (!is_array($data['taxonomies'] ?? [])) {
            throw new \InvalidArgumentException('分类分组配置无效。');
        }
        foreach (($data['taxonomies'] ?? []) as $group => $settings) {
            if (!is_array($settings) || !is_string($group)) {
                throw new \InvalidArgumentException('分类分组配置无效。');
            }
            if (array_key_exists('enabled', $settings) && !$settings['enabled']) {
                continue;
            }
            $bindings[$group] = ['multiple' => filter_var($settings['multiple'] ?? false, FILTER_VALIDATE_BOOL), 'required' => filter_var($settings['required'] ?? false, FILTER_VALIDATE_BOOL)];
        }
        $urlPattern = trim((string) ($data['urlPattern'] ?? '/{slug}'));
        ContentUrls::validatePath($urlPattern, true);
        $listEnabled = filter_var($data['listEnabled'] ?? false, FILTER_VALIDATE_BOOL);
        $listPath = trim((string) ($data['listPath'] ?? ''));
        if ($listEnabled) {
            ContentUrls::validatePath($listPath, false);
        }
        $perPage = filter_var($data['perPage'] ?? 12, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($perPage === false) {
            throw new \InvalidArgumentException('每页数量必须是 1 到 100 的整数。');
        }
        $template = trim((string) ($data['defaultTemplate'] ?? 'content-detail')) ?: 'content-detail';
        $listTemplate = trim((string) ($data['listTemplate'] ?? 'content-list')) ?: 'content-list';
        foreach ([$template, $listTemplate] as $name) {
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,99}$/D', $name)) {
                throw new \InvalidArgumentException('模板标识无效。');
            }
        }
        return new ContentTypeDefinition($handle, $label, $fields, $template, $urlPattern, (bool) ($data['hierarchical'] ?? false), $bindings, $listEnabled, $listPath, $listTemplate, $perPage);
    }

    /** @return array<string, mixed> */
    private function fieldSettings(FieldType $field): array
    {
        if ($field instanceof ContentBodyField) {
            return ['allowHtmlDocument' => $field->allowsHtmlDocument()];
        }
        $data = $field->adminData();
        if (isset($data['options']) && is_array($data['options'])) {
            $lines = [];
            foreach ($data['options'] as $value => $label) {
                $lines[] = sprintf('%s | %s', $value, $label);
            }

            return ['optionsText' => implode("\n", $lines)];
        }
        if ($field instanceof RangeField) {
            return ['rangeMin' => $data['min'], 'rangeMax' => $data['max'], 'rangeStep' => $data['step']];
        }

        // Plugin Field Type 的 factory 使用这些设置在下次启动时重建实例。
        return $data;
    }
}
