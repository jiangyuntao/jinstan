<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Theme\Queries\ThemeManagerQuery;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager;
use PHPUnit\Framework\TestCase;

final class ThemeManagerQueryTest extends TestCase
{
    public function testItBuildsTheThemeManagerViewModel(): void
    {
        $root = sys_get_temp_dir() . '/jinstan-theme-query-' . bin2hex(random_bytes(6));
        mkdir($root, 0750, true);
        $files = new ThemeFileManager($root);
        $files->createTheme('campaign', 'Campaign');
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.theme', 'campaign');

        $data = (new ThemeManagerQuery($files, $settings))->execute([
            'theme' => ['invalid'],
            'file' => 'theme.json',
        ]);

        self::assertSame(['campaign'], $data['themes']);
        self::assertSame('campaign', $data['theme']);
        self::assertSame('theme.json', $data['selected']);
        self::assertStringContainsString('"name": "Campaign"', $data['source']);
        self::assertSame('campaign', $data['activeTheme']);
        self::assertFalse($data['canDeleteTheme']);
        self::assertContains('templates/content-detail.jst', array_column($data['entries'], 'path'));
    }

    public function testItRejectsAFileOutsideTheSelectedThemeCatalog(): void
    {
        $root = sys_get_temp_dir() . '/jinstan-theme-query-' . bin2hex(random_bytes(6));
        mkdir($root, 0750, true);
        $files = new ThemeFileManager($root);
        $files->createTheme('campaign', 'Campaign');
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('theme.file_not_found');

        (new ThemeManagerQuery($files, new SettingsRepository($connection)))->execute([
            'theme' => 'campaign',
            'file' => '../outside.txt',
        ]);
    }
}
