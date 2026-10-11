<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\I18n;

use PHPUnit\Framework\TestCase;

final class AdminLanguageCatalogTest extends TestCase
{
    public function testBuiltInAdminCatalogsHaveTheSameTranslationKeys(): void
    {
        $root = dirname(__DIR__, 3) . '/core/lang/admin';
        $chinese = require $root . '/zh-CN.php';
        $korean = require $root . '/ko.php';

        $chineseKeys = array_keys($chinese);
        $koreanKeys = array_keys($korean);
        sort($chineseKeys);
        sort($koreanKeys);

        self::assertSame($chineseKeys, $koreanKeys);
    }

    public function testKoreanCatalogValuesAreFullyTranslated(): void
    {
        $root = dirname(__DIR__, 3) . '/core/lang/admin';
        $catalogs = ['Korean' => require $root . '/ko.php'];

        foreach ($catalogs as $language => $catalog) {
            foreach ($catalog as $key => $value) {
                self::assertDoesNotMatchRegularExpression(
                    '/[\x{4e00}-\x{9fff}]/u',
                    $value,
                    sprintf('%s translation for "%s" still contains Chinese characters.', $language, $key),
                );
                self::assertStringNotContainsString(
                    'Error 500',
                    $value,
                    sprintf('%s translation for "%s" contains a translation service error.', $language, $key),
                );
            }
        }

        $korean = require $root . '/ko.php';

        foreach ($korean as $key => $value) {
            if (in_array($key, ['admin.brand', 'admin.brand_short', 'admin.brand_upper', 'admin.version', 'admin.checksum'], true) || str_starts_with($key, 'examples.')) {
                continue;
            }
            self::assertMatchesRegularExpression(
                '/[가-힣]/u',
                $value,
                sprintf('Korean translation for "%s" does not contain Korean text.', $key),
            );
        }
    }

    public function testCatalogValuesAndPlaceholdersMatchChineseBaseline(): void
    {
        $root = dirname(__DIR__, 3) . '/core/lang/admin';
        $chinese = require $root . '/zh-CN.php';
        foreach (['ko'] as $locale) {
            $catalog = require $root . '/' . $locale . '.php';
            foreach ($chinese as $key => $value) {
                self::assertNotSame('', trim($catalog[$key]), $locale . ': ' . $key);
                preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $value, $expected);
                preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $catalog[$key], $actual);
                sort($expected[0]);
                sort($actual[0]);
                self::assertSame($expected[0], $actual[0], $locale . ': ' . $key);
            }
        }
    }

    public function testAdminTranslationCallsExistInTheChineseCatalog(): void
    {
        $root = dirname(__DIR__, 3);
        $catalog = require $root . '/core/lang/admin/zh-CN.php';
        $missing = [];
        foreach ([$root . '/core/src', $root . '/core/resources/views/admin'] as $sourceRoot) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));
            foreach ($files as $file) {
                if (!in_array($file->getExtension(), ['php', 'jst'], true)) {
                    continue;
                }
                $source = file_get_contents($file->getPathname());
                preg_match_all("/(?:\\bt|->translate)\\('((?:[^'\\\\]|\\\\.)*)'(?=\\s*[,)])/u", $source, $matches);
                foreach ($matches[1] as $key) {
                    $key = stripslashes($key);
                    if (!array_key_exists($key, $catalog)) {
                        $missing[$key][] = $file->getPathname();
                    }
                }
                if ($file->getExtension() === 'jst') {
                    $withoutKeys = preg_replace("/\\bt\\('(?:[^'\\\\]|\\\\.)*'(?=\\s*[,)])/u", 't(KEY', $source);
                    self::assertDoesNotMatchRegularExpression('/[\x{4e00}-\x{9fff}]/u', $withoutKeys, $file->getPathname());
                }
            }
        }

        self::assertSame([], $missing, 'Admin translation keys are missing from the Chinese catalog.');
    }
}
