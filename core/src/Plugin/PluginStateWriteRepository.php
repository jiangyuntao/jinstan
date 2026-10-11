<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

/** Internal Extension API：Plugin 安装版本与启用状态写入。 */
interface PluginStateWriteRepository
{
    public function save(string $name, string $version, bool $enabled): void;
}
