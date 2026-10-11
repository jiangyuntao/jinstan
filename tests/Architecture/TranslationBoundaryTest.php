<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class TranslationBoundaryTest extends TestCase
{
    public function testTranslationServicesDependOnCatalogLoaderContract(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/I18n';
        $sources = (string) file_get_contents($root . '/AdminLocale.php')
            . (string) file_get_contents($root . '/TranslationRegistry.php')
            . (string) file_get_contents($root . '/Translator.php');

        self::assertStringContainsString('TranslationCatalogLoader', $sources);
        self::assertStringNotContainsString('Jinstan\\Core\\Infrastructure', $sources);
        self::assertStringNotContainsString('new PhpTranslationCatalogLoader', $sources);
    }
}
