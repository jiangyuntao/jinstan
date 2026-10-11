<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Database;

use PDO;
use PDOStatement;

final class Connection
{
    public function __construct(private readonly PDO $pdo, private readonly string $driver)
    {
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function isTransactionActive(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function executeQuery(string $sql, array $parameters = []): PDOStatement
    {
        return $this->statement($sql, $parameters);
    }

    public function executeStatement(string $sql, array $parameters = []): int
    {
        $statement = $this->statement($sql, $parameters);

        return $statement->rowCount();
    }

    public function fetchOne(string $sql, array $parameters = []): mixed
    {
        return $this->statement($sql, $parameters)->fetchColumn();
    }

    public function fetchAssociative(string $sql, array $parameters = []): array|false
    {
        return $this->statement($sql, $parameters)->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAllAssociative(string $sql, array $parameters = []): array
    {
        return $this->statement($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(string $table, array $values): void
    {
        $columns = array_keys($values);
        $this->assertIdentifiers($table, ...$columns);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $this->executeStatement(sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', $placeholders)), $values);
    }

    public function update(string $table, array $values, array $criteria): void
    {
        $columns = array_keys($values);
        $criteriaColumns = array_keys($criteria);
        $this->assertIdentifiers($table, ...$columns, ...$criteriaColumns);
        $sets = array_map(static fn (string $column): string => $column . ' = :value_' . $column, $columns);
        $where = array_map(static fn (string $column): string => $column . ' = :where_' . $column, $criteriaColumns);
        $parameters = [];
        foreach ($values as $column => $value) {
            $parameters['value_' . $column] = $value;
        }
        foreach ($criteria as $column => $value) {
            $parameters['where_' . $column] = $value;
        }
        $this->executeStatement(sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), implode(' AND ', $where)), $parameters);
    }

    public function delete(string $table, array $criteria): void
    {
        $columns = array_keys($criteria);
        $this->assertIdentifiers($table, ...$columns);
        $where = array_map(static fn (string $column): string => $column . ' = :' . $column, $columns);
        $this->executeStatement(sprintf('DELETE FROM %s WHERE %s', $table, implode(' AND ', $where)), $criteria);
    }

    /** @return list<string> */
    public function tableNames(): array
    {
        return match ($this->driver) {
            'sqlite' => array_column($this->fetchAllAssociative("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"), 'name'),
            'mysql' => array_map('current', $this->fetchAllAssociative('SHOW TABLES')),
            'pgsql' => array_column($this->fetchAllAssociative('SELECT tablename FROM pg_tables WHERE schemaname = current_schema()'), 'tablename'),
        };
    }

    public function tablesExist(array $tables): bool
    {
        return $tables === array_intersect($tables, $this->tableNames());
    }

    private function statement(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement;
    }

    private function assertIdentifiers(string ...$identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
                throw new \InvalidArgumentException('无效的数据库标识符。');
            }
        }
    }
}
