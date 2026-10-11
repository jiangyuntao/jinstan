<?php

declare(strict_types=1);

namespace Jinstan\Core\Navigation;

/** Internal Extension API：导航菜单与菜单项写入契约。 */
interface NavigationWriteRepository
{
    public function createMenu(string $handle, string $name): void;

    public function createItem(
        string $handle,
        string $locale,
        string $label,
        string $url,
        string $target,
        int $position,
        ?int $parentId = null,
    ): void;

    public function updateItem(
        int $id,
        string $locale,
        string $label,
        string $url,
        string $target,
        int $position,
        ?int $parentId = null,
    ): void;

    public function deleteItem(int $id): void;

    /** @param list<int> $itemIds */
    public function reorder(string $handle, array $itemIds): void;
}
