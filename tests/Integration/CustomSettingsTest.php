<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Infrastructure\View\ThemeRenderer;
use PDOException;
use PHPUnit\Framework\TestCase;

final class CustomSettingsTest extends TestCase
{
    public function testSystemSettingsRollbackWhenCustomReplacementFails(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.name', 'Original');
        $settings->replaceCustom(['old.key' => 'keep']);
        $connection->executeStatement("CREATE TRIGGER fail_custom_insert BEFORE INSERT ON settings WHEN NEW.setting_key = 'custom.blocked' BEGIN SELECT RAISE(FAIL, 'blocked'); END");

        try {
            $settings->saveSystem(['site.name' => 'Changed'], ['blocked' => 'value']);
            self::fail('Expected custom setting insert to fail.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('blocked', $exception->getMessage());
        }

        self::assertSame('Original', $settings->get('site.name'));
        self::assertSame(['old.key' => 'keep'], $settings->custom());
        self::assertFalse($connection->isTransactionActive());
    }

    public function testCustomSettingsCanBeReplacedAndUsedByATheme(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.name', '保留的站点名称');
        $settings->replaceCustom(['company.name' => '示例公司', 'support_phone' => '400-000-0000']);
        $settings->replaceCustom(['company.name' => '新公司']);

        self::assertSame(['company.name' => '新公司'], $settings->custom());
        self::assertSame('保留的站点名称', $settings->get('site.name'));

        $projectPath = sys_get_temp_dir() . '/jinstan-custom-settings-' . bin2hex(random_bytes(6));
        $themePath = $projectPath . '/themes/custom';
        mkdir($themePath . '/templates', 0750, true);
        mkdir($projectPath . '/core/assets', 0750, true);
        mkdir($projectPath . '/core/src', 0750, true);
        mkdir($projectPath . '/storage/cache', 0750, true);
        copy(dirname(__DIR__, 2) . '/core/assets/manifest.php', $projectPath . '/core/assets/manifest.php');
        copy(dirname(__DIR__, 2) . '/core/src/Version.php', $projectPath . '/core/src/Version.php');
        file_put_contents($themePath . '/theme.json', '{"name":"Custom","homepageTemplate":"home"}');
        file_put_contents($themePath . '/templates/home.jst', '{$site[\'custom\'][\'company.name\']}');
        $renderer = new ThemeRenderer($themePath, new ContentTypeRegistry(), settings: $settings);

        self::assertSame('新公司', $renderer->homepage([], 'zh-CN', '/'));
    }
}
