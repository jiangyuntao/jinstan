<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Update;

use Jinstan\Core\Infrastructure\Filesystem\Update\UpdateWorkspace;
use PHPUnit\Framework\TestCase;

final class UpdateWorkspaceTest extends TestCase
{
    public function testItCreatesAStagingDirectoryWithASafeVersionName(): void
    {
        $root = sys_get_temp_dir() . '/jinstan-update-workspace-' . bin2hex(random_bytes(6));
        $path = (new UpdateWorkspace($root))->stagingPath('v1.2.3/../../unsafe');

        self::assertSame($root . '/staging-v1.2.3....unsafe', $path);
        self::assertDirectoryExists($path);

        rmdir($path);
        rmdir($root);
    }
}
