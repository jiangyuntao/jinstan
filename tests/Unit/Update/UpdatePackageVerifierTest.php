<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Update;

use Jinstan\Core\Infrastructure\Filesystem\Update\UpdatePackageVerifier;
use PHPUnit\Framework\TestCase;

final class UpdatePackageVerifierTest extends TestCase
{
    public function testItAcceptsOnlyAnExistingPackageWithTheExpectedHash(): void
    {
        $package = tempnam(sys_get_temp_dir(), 'jinstan-update-package-');
        self::assertNotFalse($package);
        file_put_contents($package, 'signed release');

        try {
            $verifier = new UpdatePackageVerifier();

            self::assertTrue($verifier->verify($package, hash_file('sha256', $package)));
            self::assertFalse($verifier->verify($package, str_repeat('0', 64)));
            self::assertFalse($verifier->verify($package, 'invalid'));
        } finally {
            unlink($package);
        }
    }
}
