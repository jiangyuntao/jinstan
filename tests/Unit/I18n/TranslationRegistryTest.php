<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\I18n;

use Jinstan\Core\Infrastructure\Filesystem\I18n\PhpTranslationCatalogLoader;
use Jinstan\Core\I18n\TranslationRegistry;
use Jinstan\Core\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslationRegistryTest extends TestCase
{
    public function testTranslatorResolvesNamespacedPluginCatalogAndParameters(): void
    {
        $loader = new PhpTranslationCatalogLoader();
        $translations = new TranslationRegistry($loader);
        $translations->register(
            'hello-plugin',
            dirname(__DIR__, 3) . '/plugins/HelloPlugin/lang/admin',
        );
        $translator = new Translator(
            dirname(__DIR__, 3) . '/core/lang/admin',
            static fn (): string => 'ko',
            extensions: $translations,
            loader: $loader,
        );

        self::assertSame('플러그인의 인사입니다.', $translator->translate('hello-plugin::greeting'));
        self::assertSame('Theo님, 환영합니다!', $translator->translate('hello-plugin::welcome', ['name' => 'Theo']));
        self::assertSame('hello-plugin::missing', $translator->translate('hello-plugin::missing'));
    }

    public function testPluginCatalogFallsBackToConfiguredLocale(): void
    {
        $loader = new PhpTranslationCatalogLoader();
        $translations = new TranslationRegistry($loader);
        $translations->register(
            'hello-plugin',
            dirname(__DIR__, 3) . '/plugins/HelloPlugin/lang/admin',
        );
        $translator = new Translator(
            dirname(__DIR__, 3) . '/core/lang/admin',
            static fn (): string => 'en',
            extensions: $translations,
            loader: $loader,
        );

        self::assertSame('来自插件的问候。', $translator->translate('hello-plugin::greeting'));
    }

    public function testItRejectsASecondPathForTheSameNamespace(): void
    {
        $translations = new TranslationRegistry(new PhpTranslationCatalogLoader());
        $translations->register('hello-plugin', dirname(__DIR__, 3) . '/plugins/HelloPlugin/lang/admin');

        $this->expectException(\RuntimeException::class);
        $translations->register('hello-plugin', dirname(__DIR__, 3) . '/core/lang/admin');
    }

    public function testLocaleCannotEscapeTheCatalogDirectory(): void
    {
        $translator = new Translator(
            dirname(__DIR__, 3) . '/core/lang/admin',
            static fn (): string => '../config/database',
            loader: new PhpTranslationCatalogLoader(),
        );

        self::assertSame('site.name', $translator->translate('site.name'));
    }
}
