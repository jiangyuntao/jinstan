<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Database;

interface BackupGateway
{
    /** @return array{items: list<array<string, mixed>>, page: int, pages: int, total: int} */
    public function paginate(int $page): array;

    public function create(): string;

    public function delete(string $id): void;
}
