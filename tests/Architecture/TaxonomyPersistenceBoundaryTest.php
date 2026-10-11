<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class TaxonomyPersistenceBoundaryTest extends TestCase
{
    public function testTaxonomyRepositoryDoesNotConstructSettingsRepository(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Infrastructure/Persistence/Taxonomy/TaxonomyRepository.php',
        );

        self::assertStringContainsString('SettingsReadRepository', $source);
        self::assertStringNotContainsString('new \\Jinstan\\Core\\Infrastructure\\Persistence\\Settings', $source);
        self::assertStringNotContainsString('new SettingsRepository', $source);
    }
}
