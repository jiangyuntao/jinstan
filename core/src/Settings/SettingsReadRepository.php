<?php

declare(strict_types=1);

namespace Jinstan\Core\Settings;

/** Public API：站点设置只读契约。 */
interface SettingsReadRepository
{
    public function get(string $key, ?string $default = null): ?string;

    /** @return array<string, string> */
    public function custom(): array;
}
