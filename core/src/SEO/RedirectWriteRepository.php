<?php

declare(strict_types=1);

namespace Jinstan\Core\SEO;

/** Public API：历史路径重定向写入契约。 */
interface RedirectWriteRepository
{
    public function add(string $source, string $target): void;
}
