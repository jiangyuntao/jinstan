<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Config;

use Jinstan\Core\Infrastructure\Config\PhpConfigLoader;
use PHPUnit\Framework\TestCase;

final class PhpConfigLoaderTest extends TestCase
{
    public function testItRejectsAConfigFileThatDoesNotReturnAnArray(): void
    {
        $path = sys_get_temp_dir() . '/jinstan-config-' . bin2hex(random_bytes(6));
        mkdir($path . '/config', 0750, true);
        file_put_contents($path . '/config/app.php', '<?php return "invalid";');
        file_put_contents($path . '/config/database.php', '<?php return [];');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('配置文件必须返回数组');

            (new PhpConfigLoader())->loadProject($path);
        } finally {
            unlink($path . '/config/app.php');
            unlink($path . '/config/database.php');
            rmdir($path . '/config');
            rmdir($path);
        }
    }
}
