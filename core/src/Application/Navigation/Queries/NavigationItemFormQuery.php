<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Navigation\Queries;

use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Navigation\NavigationReadRepository;
use Jinstan\Core\Settings\SettingsReadRepository;

final class NavigationItemFormQuery
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
    public function execute(?int $id, array $parameters): array
    {
        $locale = ContentLocales::requested($parameters, $this->settings);
        $item = $id === null ? null : $this->navigation->item($id, $locale);
        if ($id !== null && $item === null) {
            throw new \OutOfBoundsException('菜单项不存在。');
        }

        $requestedMenu = $parameters['menu'] ?? null;
        $menu = is_string($item['menu'] ?? null)
            ? $item['menu']
            : (is_string($requestedMenu) ? trim($requestedMenu) : '');
        if ($menu === '') {
            throw new \OutOfBoundsException('导航菜单不存在。');
        }

        $source = $id !== null && ($item['label'] ?? null) === null
            ? $this->translations->source($id, $locale)
            : null;
        if ($source !== null) {
            $source['localeName'] = $this->translations->localeName($source['locale']);
            $source['locale'] = $source['localeName'] . ' (' . $source['locale'] . ')';
        }

        return [
            'editing' => $id !== null,
            'item' => $item ?? [
                'label' => '',
                'url' => '',
                'target' => '_self',
                'position' => 0,
                'parent_id' => null,
            ],
            'menu' => $menu,
            'locale' => $locale,
            'locales' => ContentLocales::options($this->settings),
            'sourceReference' => $source,
            'parentItems' => array_values(array_filter(
                $this->navigation->items(
                    $menu,
                    $locale,
                    $this->settings->get('site.default_locale', ContentLocales::FALLBACK)
                        ?? ContentLocales::FALLBACK,
                ),
                static fn (array $candidate): bool => (int) $candidate['id'] !== $id,
            )),
        ];
    }
}
