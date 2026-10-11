<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Navigation\Queries\NavigationItemFormQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationTranslationResolver;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class NavigationItemFormQueryTest extends TestCase
{
    public function testItBuildsAnUntranslatedEditFormAndExcludesTheCurrentParentOption(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en,zh');
        $navigation = new NavigationRepository($connection);
        $navigation->createMenu('primary', '主导航');
        $navigation->createItem('primary', 'en', 'About', '/about', '_self', 10);
        $navigation->createItem('primary', 'en', 'Contact', '/contact', '_self', 20);
        $items = $navigation->items('primary', 'en');
        $id = (int) $items[0]['id'];
        $query = new NavigationItemFormQuery(
            $navigation,
            $settings,
            new NavigationTranslationResolver($navigation, $settings),
        );

        $data = $query->execute($id, ['locale' => 'zh', 'menu' => ['ignored']]);

        self::assertTrue($data['editing']);
        self::assertSame('primary', $data['menu']);
        self::assertNull($data['item']['label']);
        self::assertSame('English (en)', $data['sourceReference']['locale']);
        self::assertSame('About', $data['sourceReference']['label']);
        self::assertCount(1, $data['parentItems']);
        self::assertSame('Contact', $data['parentItems'][0]['label']);
    }

    public function testCreateFormRequiresAScalarMenuHandle(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $navigation = new NavigationRepository($connection);
        $query = new NavigationItemFormQuery(
            $navigation,
            $settings,
            new NavigationTranslationResolver($navigation, $settings),
        );

        $this->expectException(\OutOfBoundsException::class);

        $query->execute(null, ['menu' => ['invalid']]);
    }
}
