<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use PHPUnit\Framework\TestCase;

final class NavigationRepositoryTest extends TestCase
{
    public function testItemsCanBeReorderedAsOneMenu(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-navigation-' . bin2hex(random_bytes(6));
        mkdir($path, 0750, true);
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => $path . '/db.sqlite']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $navigation = new NavigationRepository($connection);
        $navigation->createMenu('primary', '主菜单');
        $navigation->createItem('primary', 'en', '首页', '/', '_self', 10);
        $navigation->createItem('primary', 'en', '关于我们', '/about', '_self', 20);
        $navigation->createItem('primary', 'en', '联系我们', '/contact', '_self', 30);

        $items = $navigation->items('primary', 'en');
        $navigation->reorder('primary', [(int) $items[2]['id'], (int) $items[0]['id'], (int) $items[1]['id']]);

        self::assertSame(['联系我们', '首页', '关于我们'], array_column($navigation->items('primary', 'en'), 'label'));
        self::assertSame([10, 20, 30], array_column($navigation->items('primary', 'en'), 'position'));

        $aboutId = (int) $items[1]['id'];
        $navigation->createItem('primary', 'en', '团队', '/team', '_self', 10, $aboutId);
        $tree = $navigation->tree('primary', 'en');
        $about = array_values(array_filter($tree, static fn (array $item): bool => $item['label'] === '关于我们'))[0];
        self::assertSame('团队', $about['children'][0]['label']);
        self::assertSame($aboutId, (int) $about['children'][0]['parent_id']);

        $this->expectException(\InvalidArgumentException::class);
        $navigation->updateItem($aboutId, 'en', '关于我们', '/about', '_self', 20, (int) $about['children'][0]['id']);
    }
}
