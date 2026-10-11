<?php

declare(strict_types=1);

namespace Jinstan\Core\Settings;

/** Internal Extension API：站点设置写入契约。 */
interface SettingsWriteRepository
{
    public function set(string $key, string $value): void;

    public function delete(string $key): void;

    /** @param array<string, string> $values */
    public function replaceCustom(array $values): void;

    /** @param array<string, string> $values
     *  @param array<string, string> $customValues
     */
    public function saveSystem(array $values, array $customValues): void;
}
