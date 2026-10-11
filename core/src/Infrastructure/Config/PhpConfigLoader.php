<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Config;

final class PhpConfigLoader
{
    /** @return array<string, mixed> */
    public function loadProject(string $projectPath): array
    {
        $app = $this->load($projectPath . '/config/app.php');
        $database = $this->load($projectPath . '/config/database.php');

        return array_replace_recursive($app, ['database' => $database]);
    }

    /** @return array<string, mixed> */
    private function load(string $path): array
    {
        $values = require $path;
        if (!is_array($values)) {
            throw new \RuntimeException(sprintf('配置文件必须返回数组：%s', $path));
        }

        return $values;
    }
}
