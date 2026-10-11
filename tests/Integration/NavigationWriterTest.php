<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Navigation\Services\NavigationWriter;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class NavigationWriterTest extends TestCase
{
    public function testItCoordinatesMenuItemWritesAndOrdering(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $navigation = new NavigationRepository($connection);
        $writer = new NavigationWriter($navigation, $settings);

        $writer->createMenu(['handle' => ' primary ', 'name' => ' 主导航 ']);
        $writer->createItem([
            'menu' => 'primary',
            'locale' => 'en',
            'label' => 'First',
            'url' => '/first',
            'target' => '_blank',
            'position' => '10',
        ]);
        $writer->createItem([
            'menu' => 'primary',
            'locale' => 'en',
            'label' => 'Second',
            'url' => '/second',
            'position' => '20',
        ]);
        $items = $navigation->items('primary', 'en');
        $firstId = (int) $items[0]['id'];
        $secondId = (int) $items[1]['id'];

        $writer->updateItem($firstId, [
            'locale' => 'en',
            'label' => 'First updated',
            'url' => '/updated',
            'target' => '_self',
            'position' => '30',
            'parent_id' => (string) $secondId,
        ]);
        $writer->reorderItems(['menu' => 'primary', 'order' => [$secondId, $firstId]]);

        $items = $navigation->items('primary', 'en');
        self::assertSame(['Second', 'First updated'], array_column($items, 'label'));
        self::assertSame([10, 20], array_column($items, 'position'));
        self::assertSame($secondId, (int) $items[1]['parent_id']);
        self::assertSame('_self', $items[1]['target']);

        $writer->deleteItem($firstId);
        self::assertCount(1, $navigation->items('primary', 'en'));
    }

    public function testItRejectsNestedItemValuesBeforePersistence(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $navigation = new NavigationRepository($connection);
        $navigation->createMenu('primary', '主导航');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('菜单项名称和链接不能为空。');

        (new NavigationWriter($navigation, new SettingsRepository($connection)))->createItem([
            'menu' => 'primary',
            'label' => ['invalid'],
            'url' => '/about',
        ]);
    }
}
