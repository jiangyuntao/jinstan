<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Navigation\Queries;

use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Navigation\NavigationReadRepository;
use Jinstan\Core\Settings\SettingsReadRepository;

final class NavigationListQuery
{
    public function __construct(
        private readonly NavigationReadRepository $navigation,
        private readonly SettingsReadRepository $settings,
        private readonly NavigationTranslationResolver $translations,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function execute(array $parameters): array
    {
        $menus = $this->navigation->menus();
        $requestedMenu = $parameters['menu'] ?? null;
        $handle = is_string($requestedMenu) ? trim($requestedMenu) : (string) ($menus[0]['handle'] ?? '');
        $locale = ContentLocales::requested($parameters, $this->settings);
        $items = $handle === '' ? [] : $this->navigation->items($handle, $locale, $locale);

        foreach ($items as &$item) {
            $item['translated'] = $item['locale'] !== null;
            if ($item['translated']) {
                continue;
            }

            $source = $this->translations->source((int) $item['id'], $locale);
            $item['label'] = $source['label'] ?? null;
            $item['url'] = $source['url'] ?? '';
            $item['sourceLocale'] = isset($source['locale'])
                ? $this->translations->localeName($source['locale']) . ' (' . $source['locale'] . ')'
                : null;
        }
        unset($item);

        return [
            'menus' => $menus,
            'selectedMenu' => $handle,
            'items' => $items,
            'locale' => $locale,
            'locales' => ContentLocales::options($this->settings),
        ];
    }
}
