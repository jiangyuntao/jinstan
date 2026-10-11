<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\System;

use Jinstan\Core\Application\Installer\EnvironmentGateway;

final class EnvironmentChecker implements EnvironmentGateway
{
    /** @return array<string, bool> */
    public function checks(): array
    {
        return [
            'PHP 8.3 或更高版本' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'PDO 扩展' => extension_loaded('pdo'),
            'PDO SQLite 扩展（默认数据库）' => extension_loaded('pdo_sqlite'),
            'JSON 扩展' => extension_loaded('json'),
            'Mbstring 扩展' => extension_loaded('mbstring'),
        ];
    }

    public function passes(): bool
    {
        return !in_array(false, $this->checks(), true);
    }
}
