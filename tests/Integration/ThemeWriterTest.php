<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Theme\Services\ThemeWriter;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager;
use PHPUnit\Framework\TestCase;

final class ThemeWriterTest extends TestCase
{
    public function testItCoordinatesConfirmedThemeAndFileOperations(): void
    {
        $root = sys_get_temp_dir() . '/jinstan-theme-writer-' . bin2hex(random_bytes(6));
        mkdir($root, 0750, true);
        $files = new ThemeFileManager($root);
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.theme', 'default');
        $writer = new ThemeWriter($files, $settings);

        self::assertSame(
            ['theme' => 'campaign', 'file' => 'theme.json'],
            $writer->createTheme(['handle' => ' campaign ', 'name' => ' Campaign ']),
        );
        $writer->createDirectory(['theme' => 'campaign', 'parent' => 'templates', 'name' => 'landing']);
        $created = $writer->createFile([
            'theme' => 'campaign',
            'directory' => 'templates/landing',
            'name' => 'offer.jst',
        ]);
        self::assertSame('templates/landing/offer.jst', $created['file']);
        $writer->saveFile([
            'theme' => 'campaign',
            'file' => $created['file'],
            'source' => "  {block content}\n",
            'confirm_overwrite' => '1',
        ]);
        self::assertSame("  {block content}\n", $files->read('campaign', $created['file']));

        $writer->deleteFile([
            'theme' => 'campaign',
            'file' => $created['file'],
            'confirm' => $created['file'],
        ]);
        $writer->deleteDirectory([
            'theme' => 'campaign',
            'directory' => 'templates/landing',
            'confirm' => 'templates/landing',
        ]);
        self::assertSame(
            ['theme' => '', 'file' => ''],
            $writer->deleteTheme(['theme' => 'campaign', 'confirm' => 'campaign']),
        );
        self::assertSame([], $files->themes());
    }

    public function testItRejectsNestedConfirmationInput(): void
    {
        $root = sys_get_temp_dir() . '/jinstan-theme-writer-' . bin2hex(random_bytes(6));
        mkdir($root, 0750, true);
        $files = new ThemeFileManager($root);
        $files->createTheme('campaign', 'Campaign');
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('theme.delete_confirmation_invalid');

        (new ThemeWriter($files, new SettingsRepository($connection)))->deleteTheme([
            'theme' => 'campaign',
            'confirm' => ['campaign'],
        ]);
    }
}
