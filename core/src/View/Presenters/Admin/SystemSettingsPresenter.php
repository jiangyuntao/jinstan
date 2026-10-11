<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Routing\ProtectedSlug;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Settings\SnsPlatforms;
use Jinstan\Core\Http\Requests\Admin\SystemSettingsRequest;

final class SystemSettingsPresenter
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly SettingsReadRepository $settings,
        private readonly SystemSettingsRequest $request,
    ) {
    }

    /** @return array<string, mixed> */
    public function present(mixed $requestedTab): array
    {
        $pages = $this->pages();
        $settingValues = $this->values([
            'site.name' => 'Jinstan',
            'site.tagline' => '',
            'site.description' => '',
            'site.contact_email' => '',
            'site.footer' => sprintf('© %s %s. 版权所有。', date('Y'), $this->settings->get('site.name', 'Jinstan') ?? 'Jinstan'),
            'site.default_locale' => 'en',
            'admin.locale' => 'zh-CN',
            'site.timezone' => 'Asia/Shanghai',
            'site.theme' => 'default',
            'site.homepage_id' => '0',
            'site.not_found_page_id' => '0',
            'site.date_format' => 'Y-m-d',
            'content.per_page' => '12',
            'content.sort' => 'newest',
            'content.default_status' => 'draft',
            'seo.default_title' => '',
            'seo.title_separator' => ' | ',
            'seo.title_homepage' => '{site_name}',
            'seo.title_content' => '{title}{separator}{site_name}',
            'seo.title_listing' => '{title}{page_suffix}{separator}{site_name}',
            'seo.title_not_found' => '{title}{separator}{site_name}',
            'seo.default_description' => '',
            'seo.robots' => 'index,follow',
            'routing.protected_slugs' => implode("\n", ProtectedSlug::DEFAULT_ROUTES),
        ]);
        $savedRoutes = preg_split('/[\s,]+/', $settingValues['routing.protected_slugs'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $savedRoutes = array_map(static fn (string $route): string => '/' . trim($route, '/'), $savedRoutes);
        $settingValues['routing.protected_slugs'] = implode("\n", array_values(array_unique([
            ...ProtectedSlug::DEFAULT_ROUTES,
            ...$savedRoutes,
        ])));
        foreach (SnsPlatforms::GROUPS as $platforms) {
            foreach ($platforms as $handle => $label) {
                $settingValues['site.sns.' . $handle] = $this->settings->get('site.sns.' . $handle, '') ?? '';
            }
        }
        foreach (SystemSettingsRequest::PREDEFINED_PAGE_SETTINGS as $definition) {
            if (!array_key_exists((int) $settingValues[$definition['setting']], $pages)) {
                $settingValues[$definition['setting']] = '0';
            }
        }

        return [
            'settings' => $settingValues,
            'timezones' => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()),
            'dateFormats' => SystemSettingsRequest::DATE_FORMATS,
            'languages' => ContentLocales::options($this->settings),
            'adminLanguages' => array_map(
                static fn (array $profile): string => $profile['name'],
                array_intersect_key(ContentLocales::catalog(), array_flip(SystemSettingsRequest::ADMIN_LOCALES)),
            ),
            'themes' => $this->request->themes(),
            'pages' => $pages,
            'predefinedPages' => SystemSettingsRequest::PREDEFINED_PAGE_SETTINGS,
            'snsPlatforms' => SnsPlatforms::GROUPS,
            'customSettings' => $this->settings->custom(),
            'activeTab' => $this->activeTab($requestedTab),
        ];
    }

    /** @return array<int, string> */
    private function pages(): array
    {
        $locale = $this->settings->get('site.default_locale', 'en') ?? 'en';
        $pages = [];
        foreach ($this->contents->list('page') as $page) {
            $translation = $this->contents->translation($page->id, $locale);
            if ($translation !== null) {
                $pages[$page->id] = $translation->title;
            }
        }

        return $pages;
    }

    private function activeTab(mixed $tab): string
    {
        return is_string($tab) && in_array($tab, SystemSettingsRequest::TABS, true) ? $tab : 'site';
    }

    /** @param array<string, string> $defaults
     *  @return array<string, string>
     */
    private function values(array $defaults): array
    {
        $values = [];
        foreach ($defaults as $key => $default) {
            $values[$key] = $this->settings->get($key, $default) ?? $default;
        }

        return $values;
    }
}
