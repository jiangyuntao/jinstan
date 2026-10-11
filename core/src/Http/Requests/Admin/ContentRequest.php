<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Requests\Admin;

use Jinstan\Core\Http\Validation\ActionRequest;
use Jinstan\Core\Http\Validation\ValidationResult;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Settings\SettingsReadRepository;

final class ContentRequest extends ActionRequest
{
    public function __construct(private readonly SettingsReadRepository $settings)
    {
    }

    public function validate(array $input): ValidationResult
    {
        $errors = [];
        foreach (['title' => '标题', 'slug' => 'Slug'] as $key => $label) {
            if (isset($input[$key]) && !is_string($input[$key])) {
                $errors[$key][] = $label . '格式无效。';
            }
            $input[$key] = $this->string($input, $key);
        }

        foreach (['data' => '内容字段', 'terms' => '分类选择'] as $key => $label) {
            if (isset($input[$key]) && !is_array($input[$key])) {
                $errors[$key][] = $label . '格式无效。';
            }
            $input[$key] = is_array($input[$key] ?? null) ? $input[$key] : [];
        }

        if (isset($input['template']) && !is_string($input['template'])) {
            $errors['template'][] = '详情模板格式无效。';
            $input['template'] = '';
        }
        if (isset($input['parent_id']) && (!is_scalar($input['parent_id']) || filter_var($input['parent_id'], FILTER_VALIDATE_INT) === false)) {
            $errors['parent_id'][] = '上级内容格式无效。';
            $input['parent_id'] = '';
        }
        if (array_key_exists('locale', $input) && (!is_string($input['locale']) || !in_array($input['locale'], ContentLocales::enabled($this->settings), true))) {
            $errors['locale'][] = '内容语言无效。';
            $input['locale'] = $this->settings->get('site.default_locale', ContentLocales::FALLBACK) ?? ContentLocales::FALLBACK;
        }
        if (array_key_exists('published', $input) && (!is_scalar($input['published']) || filter_var($input['published'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null)) {
            $errors['published'][] = '发布状态无效。';
            $input['published'] = false;
        }
        foreach (['remove_media', 'manage_media', 'media_order'] as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            if (!is_array($input[$key])) {
                $errors[$key][] = '媒体操作格式无效。';
                $input[$key] = [];
                continue;
            }
            foreach ($input[$key] as $field => $value) {
                $valid = $key === 'media_order'
                    ? is_array($value) && count($value) <= 1000 && array_is_list($value) && count(array_filter($value, 'is_string')) === count($value)
                    : is_string($value) && in_array($value, $key === 'remove_media' ? ['0', '1'] : ['1'], true);
                if (!is_string($field) || !$valid) {
                    $errors[$key][] = '媒体操作格式无效。';
                    $input[$key] = [];
                    break;
                }
            }
        }

        // 动态 data 内部的值由对应 Field Type 处理，此处只检查表单外层结构。
        return new ValidationResult($input, $errors);
    }
}
