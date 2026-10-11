<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Requests\Admin;

use Jinstan\Core\Application\Theme\ThemeSelectionGateway;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Http\Validation\ActionRequest;
use Jinstan\Core\Http\Validation\ValidationResult;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Settings\SnsPlatforms;

final class SystemSettingsRequest extends ActionRequest
{
    public const PREDEFINED_PAGE_SETTINGS = [
        'homepage_id' => [
            'setting' => 'site.homepage_id',
            'label' => 'settings.homepage',
            'empty' => '使用主题默认首页',
            'help' => '未选择页面时，首页使用当前主题声明的首页模板，并展示已启用列表页的最新内容。',
        ],
        'not_found_page_id' => [
            'setting' => 'site.not_found_page_id',
            'label' => 'settings.not_found_page',
            'empty' => '使用系统默认 404',
            'help' => '页面不存在时显示所选页面的内容，同时保持 HTTP 404 状态。',
        ],
    ];

    public const DATE_FORMATS = [
        'Y-m-d' => '2026-09-06',
        'd/m/Y' => '06/09/2026',
        'm/d/Y' => '09/06/2026',
        'Y年m月d日' => '2026年09月06日',
        'F j, Y' => 'September 6, 2026',
    ];

    public const TABS = ['site', 'locale', 'theme', 'content', 'predefined', 'seo', 'sns', 'urls', 'custom'];

    public const ADMIN_LOCALES = ['zh-CN', 'ko'];

    public function __construct(
        private readonly SettingsReadRepository $settings,
        private readonly ThemeSelectionGateway $themes,
        private readonly ContentReadRepository $contents,
    ) {
    }

    public function validate(array $input): ValidationResult
    {
        $siteName = $this->limited($input, 'site_name', 255);
        $contactEmail = $this->limited($input, 'contact_email', 255);
        $frontendLocale = $this->string($input, 'frontend_locale');
        $adminLocale = $this->string($input, 'admin_locale');
        $theme = $this->string($input, 'theme');
        $timezone = $this->string($input, 'timezone');
        $dateFormat = $this->stringValue($input['date_format'] ?? 'Y-m-d');
        $contentPerPage = filter_var(
            $input['content_per_page'] ?? $this->settings->get('content.per_page', '12'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]],
        );
        $contentSort = $this->stringValue(
            $input['content_sort'] ?? $this->settings->get('content.sort', 'newest'),
        );
        $defaultContentStatus = $this->stringValue(
            $input['default_content_status'] ?? $this->settings->get('content.default_status', 'draft'),
        );
        $protectedSlugs = $this->protectedSlugs($input['protected_slugs'] ?? '');
        $errors = [];
        try {
            $seoValues = $this->seoValues($input);
        } catch (\InvalidArgumentException $exception) {
            $errors['seo'][] = $exception->getMessage();
            $seoValues = [];
        }
        try {
            $snsValues = $this->snsValues($input);
        } catch (\InvalidArgumentException $exception) {
            $errors['sns'][] = $exception->getMessage();
            $snsValues = [];
        }
        try {
            $customValues = $this->customValues($input);
        } catch (\InvalidArgumentException $exception) {
            $errors['custom_keys'][] = $exception->getMessage();
            $customValues = [];
        }
        if ($siteName === '') {
            $errors['site_name'][] = '站点名称不能为空。';
        }
        if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['contact_email'][] = '联系邮箱格式无效。';
        }
        if (!array_key_exists($frontendLocale, ContentLocales::options($this->settings))) {
            $errors['frontend_locale'][] = '前台语言设置无效。';
        }
        if (!in_array($adminLocale, self::ADMIN_LOCALES, true)) {
            $errors['admin_locale'][] = '后台语言设置无效。';
        }
        if (!array_key_exists($theme, $this->themes())) {
            $errors['theme'][] = '前台主题无效。';
        } elseif (!$this->themes->hasHomepage($theme)) {
            $errors['theme'][] = '前台主题缺少可用的首页模板。';
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'][] = '时区无效。';
        }
        if (!array_key_exists($dateFormat, self::DATE_FORMATS)) {
            $errors['date_format'][] = '日期格式无效。';
        }
        if ($contentPerPage === false) {
            $errors['content_per_page'][] = '每页内容数目必须在 1 到 100 之间。';
        }
        if (!in_array($contentSort, ['newest', 'oldest', 'title_asc', 'title_desc'], true)) {
            $errors['content_sort'][] = '内容排序方式无效。';
        }
        if (!in_array($defaultContentStatus, ['draft', 'published'], true)) {
            $errors['default_content_status'][] = '默认内容状态无效。';
        }
        if ($protectedSlugs === null) {
            $errors['protected_slugs'][] = '保留路由必须一行一个、以 / 开头，且只能包含字母、数字、点、连字符、下划线和路径分隔符。';
            $protectedSlugs = [];
        }
        $predefinedPages = [];
        foreach (self::PREDEFINED_PAGE_SETTINGS as $inputName => $definition) {
            $pageId = $this->integer($input[$inputName] ?? 0);
            if ($pageId === null || $pageId < 0) {
                $errors[$inputName][] = '预定义页面选择无效。';
                $pageId = 0;
            } elseif ($pageId > 0 && ($this->contents->find($pageId)?->type !== 'page')) {
                $errors[$inputName][] = '预定义页面必须选择页面类型的内容。';
            }
            $predefinedPages[$definition['setting']] = (string) $pageId;
        }

        return new ValidationResult([
            'site_name' => $siteName,
            'site_tagline' => $this->limited($input, 'site_tagline', 160),
            'site_description' => $this->limited($input, 'site_description', 320),
            'contact_email' => $contactEmail,
            'site_footer' => $this->limited($input, 'site_footer', 500),
            'frontend_locale' => $frontendLocale,
            'admin_locale' => $adminLocale,
            'timezone' => $timezone,
            'date_format' => $dateFormat,
            'content_per_page' => $contentPerPage === false ? 0 : $contentPerPage,
            'content_sort' => $contentSort,
            'default_content_status' => $defaultContentStatus,
            'theme' => $theme,
            'active_tab' => $this->activeTab($input['active_tab'] ?? 'site'),
            'predefined_pages' => $predefinedPages,
            'protected_slugs' => $protectedSlugs,
            'seo_values' => $seoValues,
            'sns_values' => $snsValues,
            'custom_values' => $customValues,
        ], $errors);
    }

    /** @param array<string, mixed> $input */
    private function limited(array $input, string $key, int $length): string
    {
        return mb_substr($this->string($input, $key), 0, $length);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }

    private function activeTab(mixed $value): string
    {
        $tab = $this->stringValue($value);

        return in_array($tab, self::TABS, true) ? $tab : 'site';
    }

    private function integer(mixed $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    /** @return list<string>|null */
    private function protectedSlugs(mixed $value): ?array
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $paths = [];
        foreach (preg_split('/\R/', (string) $value) ?: [] as $path) {
            $path = trim($path);
            if ($path === '') {
                continue;
            }
            $path = mb_strtolower(rtrim($path, '/'));
            if (preg_match('~^/(?:[a-z0-9_.-]+/)*[a-z0-9_.-]+$~D', $path) !== 1) {
                return null;
            }
            $paths[$path] = $path;
        }

        return array_values($paths);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function seoValues(array $input): array
    {
        $robots = $this->settingValue($input, 'robots', 'seo.robots', 'index,follow');
        if (!in_array($robots, ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)) {
            throw new \InvalidArgumentException('robots 设置无效。');
        }

        $titleTemplates = [
            'seo.title_homepage' => $this->settingValue($input, 'title_homepage', 'seo.title_homepage', '{site_name}'),
            'seo.title_content' => $this->settingValue($input, 'title_content', 'seo.title_content', '{title}{separator}{site_name}'),
            'seo.title_listing' => $this->settingValue($input, 'title_listing', 'seo.title_listing', '{title}{page_suffix}{separator}{site_name}'),
            'seo.title_not_found' => $this->settingValue($input, 'title_not_found', 'seo.title_not_found', '{title}{separator}{site_name}'),
        ];
        foreach ($titleTemplates as $template) {
            if ($template === '' || mb_strlen($template) > 255 || preg_match_all('/\{([^{}]+)\}/', $template, $matches) === false) {
                throw new \InvalidArgumentException('标题格式不能为空，且不能超过 255 个字符。');
            }
            $unknown = array_diff($matches[1], ['site_name', 'tagline', 'title', 'content_type', 'page', 'page_suffix', 'separator']);
            if ($unknown !== []) {
                throw new \InvalidArgumentException('标题格式包含不支持的占位符：{' . reset($unknown) . '}。');
            }
            $plain = preg_replace('/\{(?:site_name|tagline|title|content_type|page|page_suffix|separator)\}/', '', $template);
            if (preg_match('/[{}]/', $plain ?? '') === 1) {
                throw new \InvalidArgumentException('标题格式中的占位符语法无效。');
            }
        }

        return [
            'seo.default_title' => mb_substr(trim($this->settingValue($input, 'default_title', 'seo.default_title')), 0, 255),
            // 分隔符的前后空格是展示内容，不做 trim。
            'seo.title_separator' => mb_substr($this->settingValue($input, 'title_separator', 'seo.title_separator', ' | ', false), 0, 20),
            ...$titleTemplates,
            'seo.default_description' => mb_substr(trim($this->settingValue($input, 'default_description', 'seo.default_description')), 0, 320),
            'seo.robots' => $robots,
        ];
    }

    /** @param array<string, mixed> $input */
    private function settingValue(
        array $input,
        string $inputName,
        string $settingName,
        string $default = '',
        bool $trim = true,
    ): string {
        $value = array_key_exists($inputName, $input)
            ? $input[$inputName]
            : $this->settings->get($settingName, $default);
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }

        $value = (string) $value;

        return $trim ? trim($value) : $value;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function snsValues(array $input): array
    {
        $submitted = $input['sns'] ?? [];
        if (!is_array($submitted)) {
            throw new \InvalidArgumentException('社交媒体地址格式无效。');
        }

        $values = [];
        foreach (SnsPlatforms::GROUPS as $platforms) {
            foreach ($platforms as $handle => $label) {
                $url = $submitted[$handle] ?? $this->settings->get('site.sns.' . $handle, '');
                if (!is_string($url)) {
                    throw new \InvalidArgumentException($label . ' 地址格式无效。');
                }
                $url = trim($url);
                if ($url !== '' && !SnsPlatforms::isValidUrl($url)) {
                    throw new \InvalidArgumentException($label . ' 地址必须是完整的 HTTP 或 HTTPS URL。');
                }
                $values['site.sns.' . $handle] = mb_substr($url, 0, 2048);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function customValues(array $input): array
    {
        $keys = $input['custom_keys'] ?? [];
        $values = $input['custom_values'] ?? [];
        if (!is_array($keys) || !is_array($values)) {
            throw new \InvalidArgumentException('自定义设置格式无效。');
        }
        if (count($keys) > 100) {
            throw new \InvalidArgumentException('自定义设置不能超过 100 项。');
        }

        $custom = [];
        foreach ($keys as $index => $rawKey) {
            if (!is_string($rawKey)) {
                throw new \InvalidArgumentException('自定义设置键格式无效。');
            }
            $key = trim($rawKey);
            if ($key === '') {
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $key) !== 1) {
                throw new \InvalidArgumentException('自定义设置键必须以小写字母开头，且只能包含小写字母、数字、点、连字符和下划线。');
            }
            if (array_key_exists($key, $custom)) {
                throw new \InvalidArgumentException('自定义设置键不能重复：' . $key . '。');
            }
            $value = $values[$index] ?? '';
            if (!is_string($value)) {
                throw new \InvalidArgumentException('自定义设置值格式无效。');
            }
            $custom[$key] = mb_substr($value, 0, 10000);
        }

        ksort($custom);

        return $custom;
    }

    /** @return array<string, string> */
    public function themes(): array
    {
        return $this->themes->options();
    }
}
