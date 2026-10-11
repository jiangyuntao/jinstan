<?php

declare(strict_types=1);

namespace Jinstan\Core\Database\Schema;

final class Schema
{
    /** @var array<string, Table> */
    private array $tables = [];

    public function createTable(string $name): Table
    {
        return $this->tables[$name] = new Table($name);
    }
    public function dropTable(string $name): void
    {
        unset($this->tables[$name]);
    }

    /** @return list<string> */
    public function statements(string $driver): array
    {
        $sql = [];
        foreach ($this->tables as $table) {
            $sql[] = $table->createSql($driver);
            foreach ($table->indexSql($driver) as $statement) {
                $sql[] = $statement;
            }
        }
        return $sql;
    }
}
