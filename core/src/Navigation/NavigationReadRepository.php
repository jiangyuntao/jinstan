<?php

declare(strict_types=1);

namespace Jinstan\Core\Navigation;

/** Public API：导航菜单与翻译的只读契约。 */
interface NavigationReadRepository
{
    /** @return list<array{id: int, handle: string, name: string}> */
    public function menus(): array;

    /** @return list<array<string, mixed>> */
    public function items(string $handle, string $locale, string $fallbackLocale = 'en'): array;

    /** @return list<array<string, mixed>> */
    public function tree(string $handle, string $locale, string $fallbackLocale = 'en'): array;

    /** @return array<string, mixed>|null */
    public function item(int $id, string $locale): ?array;

    /** @return list<array<string, mixed>> */
    public function translations(int $id): array;
}
