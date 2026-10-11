<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Update;

final class UpdatePackageVerifier
{
    public function verify(string $package, string $expectedSha256): bool
    {
        return is_file($package) && preg_match('/^[a-f0-9]{64}$/i', $expectedSha256) === 1 && hash_equals(strtolower($expectedSha256), hash_file('sha256', $package));
    }
}
