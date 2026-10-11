<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Update;

interface CoreUpdateStorage
{
    public function assertValidCore(string $core): void;

    public function release(string $core): string;

    public function stagedAssets(string $stagedCore, string $release): string;

    public function corePath(): string;

    public function assetReleasePath(string $release): string;

    public function uniqueUpdatePath(string $prefix): string;

    public function directoryExists(string $path): bool;

    public function move(string $source, string $target, string $error): void;

    public function healthy(string $core): bool;

    public function publishAssets(string $source, string $manifestCore): bool;

    public function verifyAssets(string $directory, string $manifestCore): void;

    public function removeDirectory(string $directory): void;
}
