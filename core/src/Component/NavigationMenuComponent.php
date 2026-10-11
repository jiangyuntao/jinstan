<?php

declare(strict_types=1);

namespace Jinstan\Core\Component;

use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Navigation\NavigationReadRepository;
use Jinstan\Core\Settings\SettingsReadRepository;

final class NavigationMenuComponent implements Component
{
    public function __construct(private readonly NavigationReadRepository $navigation, private readonly ?SettingsReadRepository $settings = null)
    {
    }

    public function name(): string
    {
        return 'navigation-menu';
    }

    public function data(array $parameters, array $context): array
    {
        $handle = trim((string) ($parameters['handle'] ?? 'primary'));
        $translation = $context['translation'] ?? null;
        $requestedLocale = trim((string) ($parameters['locale'] ?? ''));
        $locale = $requestedLocale !== ''
            ? $requestedLocale
            : ($translation instanceof ContentTranslation
                ? $translation->locale
                : ($this->settings?->get('site.default_locale', ContentLocales::FALLBACK) ?? ContentLocales::FALLBACK));

        $fallbackLocale = ContentLocales::FALLBACK;

        return ['items' => $this->navigation->tree($handle, $locale, $fallbackLocale)];
    }
}
