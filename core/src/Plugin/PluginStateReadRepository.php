<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

/** Internal Extension API：Plugin 安装版本与启用状态查询。 */
interface PluginStateReadRepository
{
    public function version(string $name): ?string;

    public function enabled(string $name): bool;
}
