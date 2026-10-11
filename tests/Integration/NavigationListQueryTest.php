<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Navigation\Queries\NavigationListQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationTranslationResolver;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class NavigationListQueryTest extends TestCase
{
    public function testItUsesTheDefaultMenuAndPreferredSourceTranslation(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en,zh');
        $navigation = new NavigationRepository($connection);
        $navigation->createMenu('primary', '主导航');
        $navigation->createItem('primary', 'en', 'About', '/about', '_self', 10);
        $translations = new NavigationTranslationResolver($navigation, $settings);

        $data = (new NavigationListQuery($navigation, $settings, $translations))->execute([
            'menu' => ['invalid'],
            'locale' => 'zh',
        ]);

        self::assertSame('primary', $data['selectedMenu']);
        self::assertSame('zh', $data['locale']);
        self::assertFalse($data['items'][0]['translated']);
        self::assertSame('About', $data['items'][0]['label']);
        self::assertSame('/about', $data['items'][0]['url']);
        self::assertSame('English (en)', $data['items'][0]['sourceLocale']);
        self::assertArrayHasKey('zh', $data['locales']);
    }
}
