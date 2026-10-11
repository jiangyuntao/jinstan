<?php

declare(strict_types=1);

namespace Jinstan\Core\I18n;

use Jinstan\Core\Settings\SettingsReadRepository;

final class ContentLocales
{
    public const FALLBACK = 'zh-CN';

    /** @return list<string> */
    public static function enabled(SettingsReadRepository $settings): array
    {
        $profiles = self::profiles($settings);
        if ($profiles !== []) {
            return array_keys($profiles);
        }
        $default = $settings->get('site.default_locale', self::FALLBACK) ?? self::FALLBACK;
        $configured = preg_split(
            '/[\s,]+/',
            $settings->get('site.enabled_locales', $default) ?? $default,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        return array_values(array_unique([self::FALLBACK, 'ko', 'en', $default, ...($configured ?: [])]));
    }

    public static function requested(array $input, SettingsReadRepository $settings): string
    {
        $locale = is_string($input['locale'] ?? null) ? $input['locale'] : '';

        return in_array($locale, self::enabled($settings), true)
            ? $locale
            : ($settings->get('site.default_locale', self::FALLBACK) ?? self::FALLBACK);
    }

    /** @return array<string, string> */
    public static function options(SettingsReadRepository $settings): array
    {
        return array_map(static fn (array $profile): string => $profile['name'], self::profiles($settings));
    }

    /** @return array<string, array{name: string, direction: string}> */
    public static function profiles(SettingsReadRepository $settings): array
    {
        $saved = json_decode($settings->get('site.locales', '{}') ?? '{}', true);
        if (is_array($saved) && $saved !== []) {
            $profiles = [];
            foreach ($saved as $code => $profile) {
                if (is_string($code) && is_array($profile) && isset($profile['name'], $profile['direction'])) {
                    $profiles[$code] = [
                        'name' => (string) $profile['name'],
                        'direction' => $profile['direction'] === 'rtl' ? 'rtl' : 'ltr',
                    ];
                }
            }
            if ($profiles !== []) {
                return $profiles;
            }
        }

        $catalog = self::catalog();
        $profiles = [];
        foreach (self::configuredCodes($settings) as $code) {
            $profiles[$code] = $catalog[$code] ?? ['name' => $code, 'direction' => 'ltr'];
        }

        return $profiles;
    }

    /** @return array<string, array{name: string, direction: string}> */
    public static function catalog(): array
    {
        $names = [
            'zh-CN' => '简体中文', 'zh-TW' => '繁體中文',
            'en' => 'English', 'en-US' => 'English (United States)', 'en-GB' => 'English (United Kingdom)',
            'ko' => '한국어', 'ja' => '日本語',
            'es' => 'Español', 'es-MX' => 'Español (México)',
            'fr' => 'Français', 'fr-CA' => 'Français (Canada)',
            'de' => 'Deutsch', 'it' => 'Italiano',
            'pt' => 'Português', 'pt-BR' => 'Português (Brasil)',
            'ru' => 'Русский', 'uk' => 'Українська', 'be' => 'Беларуская',
            'ar' => 'العربية', 'fa' => 'فارسی', 'he' => 'עברית', 'ur' => 'اردو', 'ps' => 'پښتو',
            'hi' => 'हिन्दी', 'bn' => 'বাংলা', 'pa' => 'ਪੰਜਾਬੀ', 'gu' => 'ગુજરાતી',
            'mr' => 'मराठी', 'ne' => 'नेपाली', 'si' => 'සිංහල', 'ta' => 'தமிழ்',
            'te' => 'తెలుగు', 'kn' => 'ಕನ್ನಡ', 'ml' => 'മലയാളം',
            'id' => 'Bahasa Indonesia', 'ms' => 'Bahasa Melayu', 'fil' => 'Filipino',
            'th' => 'ไทย', 'vi' => 'Tiếng Việt', 'km' => 'ខ្មែរ', 'lo' => 'ລາວ', 'my' => 'မြန်မာ',
            'tr' => 'Türkçe', 'az' => 'Azərbaycanca', 'ka' => 'ქართული', 'hy' => 'Հայերեն',
            'kk' => 'Қазақша', 'uz' => 'O‘zbekcha', 'mn' => 'Монгол',
            'nl' => 'Nederlands', 'pl' => 'Polski', 'cs' => 'Čeština', 'sk' => 'Slovenčina',
            'hu' => 'Magyar', 'ro' => 'Română', 'bg' => 'Български', 'sr' => 'Српски',
            'hr' => 'Hrvatski', 'sl' => 'Slovenščina', 'el' => 'Ελληνικά',
            'sv' => 'Svenska', 'no' => 'Norsk', 'da' => 'Dansk', 'fi' => 'Suomi', 'is' => 'Íslenska',
            'et' => 'Eesti', 'lv' => 'Latviešu', 'lt' => 'Lietuvių',
            'ca' => 'Català', 'eu' => 'Euskara', 'gl' => 'Galego',
            'af' => 'Afrikaans', 'sw' => 'Kiswahili', 'am' => 'አማርኛ', 'zu' => 'isiZulu',
        ];
        $rtl = ['ar', 'fa', 'he', 'ur', 'ps'];
        $catalog = [];
        foreach ($names as $code => $name) {
            $catalog[$code] = ['name' => $name, 'direction' => in_array($code, $rtl, true) ? 'rtl' : 'ltr'];
        }

        return $catalog;
    }

    public static function direction(string $locale, SettingsReadRepository $settings): string
    {
        return self::profiles($settings)[$locale]['direction'] ?? 'ltr';
    }

    /** @return list<string> */
    private static function configuredCodes(SettingsReadRepository $settings): array
    {
        $default = $settings->get('site.default_locale', self::FALLBACK) ?? self::FALLBACK;
        $configured = preg_split('/[\s,]+/', $settings->get('site.enabled_locales', $default) ?? $default, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique([$default, ...($configured ?: []), self::FALLBACK]));
    }
}
