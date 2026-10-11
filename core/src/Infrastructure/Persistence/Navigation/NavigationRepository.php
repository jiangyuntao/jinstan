<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Navigation;

use DateTimeImmutable;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Navigation\NavigationRepositoryContract;

final class NavigationRepository implements NavigationRepositoryContract
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function createMenu(string $handle, string $name): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->insert('navigation_menus', [
            'handle' => $handle,
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function createItem(string $handle, string $locale, string $label, string $url, string $target, int $position, ?int $parentId = null): void
    {
        $menuId = $this->menuId($handle);
        $this->assertParent($menuId, $parentId);
        $this->connection->insert('navigation_menu_items', ['menu_id' => $menuId, 'parent_id' => $parentId, 'position' => $position]);
        $itemId = (int) $this->connection->lastInsertId();
        $this->connection->insert('navigation_menu_item_translations', [
            'item_id' => $itemId,
            'locale' => $locale,
            'label' => $label,
            'url' => $url,
        ]);
        $this->connection->insert('navigation_menu_item_targets', ['item_id' => $itemId, 'target' => $this->target($target)]);
    }

    public function updateItem(int $id, string $locale, string $label, string $url, string $target, int $position, ?int $parentId = null): void
    {
        $menuId = $this->connection->fetchOne('SELECT menu_id FROM navigation_menu_items WHERE id = ?', [$id]);
        if ($menuId === false) {
            throw new \InvalidArgumentException('菜单项不存在。');
        }
        $this->assertParent((int) $menuId, $parentId, $id);
        $this->connection->update('navigation_menu_items', ['position' => $position, 'parent_id' => $parentId], ['id' => $id]);
        $targetExists = $this->connection->fetchOne('SELECT item_id FROM navigation_menu_item_targets WHERE item_id = ?', [$id]);
        if ($targetExists === false) {
            $this->connection->insert('navigation_menu_item_targets', ['item_id' => $id, 'target' => $this->target($target)]);
        } else {
            $this->connection->update('navigation_menu_item_targets', ['target' => $this->target($target)], ['item_id' => $id]);
        }
        $exists = $this->connection->fetchOne('SELECT item_id FROM navigation_menu_item_translations WHERE item_id = ? AND locale = ?', [$id, $locale]);
        if ($exists === false) {
            $this->connection->insert('navigation_menu_item_translations', ['item_id' => $id, 'locale' => $locale, 'label' => $label, 'url' => $url]);

            return;
        }
        $this->connection->update('navigation_menu_item_translations', ['label' => $label, 'url' => $url], ['item_id' => $id, 'locale' => $locale]);
    }

    public function deleteItem(int $id): void
    {
        $this->connection->delete('navigation_menu_items', ['id' => $id]);
    }

    /** @param list<int> $itemIds */
    public function reorder(string $handle, array $itemIds): void
    {
        $menuId = $this->menuId($handle);
        $existingIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $this->connection->fetchAllAssociative('SELECT id FROM navigation_menu_items WHERE menu_id = ? ORDER BY id', [$menuId]),
        );
        $submittedIds = array_values(array_unique($itemIds));
        sort($existingIds);
        sort($submittedIds);
        if ($existingIds !== $submittedIds) {
            throw new \InvalidArgumentException('菜单排序项目无效。');
        }

        $this->connection->beginTransaction();
        try {
            foreach ($itemIds as $index => $itemId) {
                $this->connection->update('navigation_menu_items', ['position' => ($index + 1) * 10], ['id' => $itemId]);
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @return list<array{id: int, handle: string, name: string}> */
    public function menus(): array
    {
        return $this->connection->fetchAllAssociative('SELECT id, handle, name FROM navigation_menus ORDER BY name ASC');
    }

    /** @return list<array{id: int, label: string, url: string, target: string, position: int}> */
    public function items(string $handle, string $locale, string $fallbackLocale = 'en'): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT navigation_menu_items.id, navigation_menu_items.parent_id, localized.locale, COALESCE(localized.label, fallback.label) AS label, COALESCE(localized.url, fallback.url) AS url, COALESCE(navigation_menu_item_targets.target, '_self') AS target, navigation_menu_items.position FROM navigation_menu_items INNER JOIN navigation_menus ON navigation_menus.id = navigation_menu_items.menu_id LEFT JOIN navigation_menu_item_translations localized ON localized.item_id = navigation_menu_items.id AND localized.locale = ? LEFT JOIN navigation_menu_item_translations fallback ON fallback.item_id = navigation_menu_items.id AND fallback.locale = ? LEFT JOIN navigation_menu_item_targets ON navigation_menu_item_targets.item_id = navigation_menu_items.id WHERE navigation_menus.handle = ? ORDER BY navigation_menu_items.position ASC, navigation_menu_items.id ASC",
            [$locale, $fallbackLocale, $handle],
        );
    }

    /** @return list<array<string, mixed>> */
    public function tree(string $handle, string $locale, string $fallbackLocale = 'en'): array
    {
        $items = $this->items($handle, $locale, $fallbackLocale);
        $children = [];
        foreach ($items as $item) {
            $children[(int) ($item['parent_id'] ?? 0)][] = $item;
        }
        $build = function (int $parentId) use (&$build, $children): array {
            return array_map(function (array $item) use (&$build): array {
                $item['children'] = $build((int) $item['id']);

                return $item;
            }, $children[$parentId] ?? []);
        };

        return $build(0);
    }

    public function item(int $id, string $locale): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT navigation_menu_items.id, navigation_menu_items.parent_id, navigation_menus.handle AS menu, navigation_menu_items.position, COALESCE(navigation_menu_item_targets.target, '_self') AS target, navigation_menu_item_translations.label, navigation_menu_item_translations.url FROM navigation_menu_items INNER JOIN navigation_menus ON navigation_menus.id = navigation_menu_items.menu_id LEFT JOIN navigation_menu_item_translations ON navigation_menu_item_translations.item_id = navigation_menu_items.id AND navigation_menu_item_translations.locale = ? LEFT JOIN navigation_menu_item_targets ON navigation_menu_item_targets.item_id = navigation_menu_items.id WHERE navigation_menu_items.id = ?",
            [$locale, $id],
        );

        return $row ?: null;
    }

    public function translations(int $id): array
    {
        return $this->connection->fetchAllAssociative('SELECT locale, label, url FROM navigation_menu_item_translations WHERE item_id = ? ORDER BY locale', [$id]);
    }

    private function menuId(string $handle): int
    {
        $id = $this->connection->fetchOne('SELECT id FROM navigation_menus WHERE handle = ?', [$handle]);
        if ($id === false) {
            throw new \InvalidArgumentException('导航菜单不存在。');
        }

        return (int) $id;
    }

    private function assertParent(int $menuId, ?int $parentId, ?int $itemId = null): void
    {
        if ($parentId === null) {
            return;
        }
        $parentMenuId = $this->connection->fetchOne('SELECT menu_id FROM navigation_menu_items WHERE id = ?', [$parentId]);
        if ($parentId === $itemId || $parentMenuId === false || (int) $parentMenuId !== $menuId) {
            throw new \InvalidArgumentException('上级菜单项无效。');
        }
        $current = $parentId;
        while ($current !== null) {
            if ($current === $itemId) {
                throw new \InvalidArgumentException('菜单层级不能形成循环。');
            }
            $value = $this->connection->fetchOne('SELECT parent_id FROM navigation_menu_items WHERE id = ?', [$current]);
            $current = $value === false || $value === null ? null : (int) $value;
        }
    }

    private function target(string $target): string
    {
        return in_array($target, ['_self', '_blank'], true) ? $target : '_self';
    }
}
