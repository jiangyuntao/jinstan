<?php

declare(strict_types=1);

namespace Jinstan\Core\SEO;

/** Public API：历史路径重定向查询契约。 */
interface RedirectReadRepository
{
    public function target(string $source): ?string;
}
