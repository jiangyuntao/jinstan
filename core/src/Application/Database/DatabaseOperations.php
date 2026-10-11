<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Database;

interface DatabaseOperations
{
    public function driver(): string;

    public function tableCount(): int;

    public function migrationCount(): int;

    /** @return array{value: string|null, translation: string|null} */
    public function integrity(): array;

    public function optimize(): void;

    public function cleanup(): int;
}
