<?php

declare(strict_types=1);

namespace Jinstan\Core\Database\Schema;

final class Table
{
    private array $columns = [];
    private array $primary = [];
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(private readonly string $name)
    {
    }
    public function addColumn(string $name, string $type, array $options = []): void
    {
        $this->columns[$name] = [$type, $options];
    }
    public function setPrimaryKey(array $columns): void
    {
        $this->primary = $columns;
    }
    public function addUniqueIndex(array $columns, string $name): void
    {
        $this->indexes[] = [$columns, $name, true];
    }
    public function addIndex(array $columns, string $name): void
    {
        $this->indexes[] = [$columns, $name, false];
    }
    public function addForeignKeyConstraint(string $table, array $local, array $foreign, array $options = []): void
    {
        $this->foreignKeys[] = [$table, $local, $foreign, $options];
    }

    public function createSql(string $driver): string
    {
        $definitions = [];
        foreach ($this->columns as $name => [$type, $options]) {
            $definitions[] = $name . ' ' . $this->columnType($type, $options, $driver) . (($options['notnull'] ?? true) ? ' NOT NULL' : '') . (array_key_exists('default', $options) ? ' DEFAULT ' . $this->default($options['default']) : '');
        }
        if ($this->primary !== []) {
            $definitions[] = 'PRIMARY KEY (' . implode(', ', $this->primary) . ')';
        }
        foreach ($this->foreignKeys as [$table, $local, $foreign, $options]) {
            $definitions[] = 'FOREIGN KEY (' . implode(', ', $local) . ') REFERENCES ' . $table . ' (' . implode(', ', $foreign) . ')' . (isset($options['onDelete']) ? ' ON DELETE ' . $options['onDelete'] : '');
        }
        return 'CREATE TABLE ' . $this->name . ' (' . implode(', ', $definitions) . ')';
    }

    /** @return list<string> */
    public function indexSql(string $driver): array
    {
        return array_map(fn (array $index): string => 'CREATE ' . ($index[2] ? 'UNIQUE ' : '') . 'INDEX ' . $index[1] . ' ON ' . $this->name . ' (' . implode(', ', $index[0]) . ')', $this->indexes);
    }

    private function columnType(string $type, array $options, string $driver): string
    {
        if (($options['autoincrement'] ?? false) && $type === 'integer') {
            // 自增列语法是三种目标数据库少数无法共用的 DDL 差异。
            return match ($driver) {
                'sqlite' => 'INTEGER', 'mysql' => 'INT AUTO_INCREMENT', 'pgsql' => 'SERIAL'
            };
        }
        return match ($type) {
            'integer' => 'INTEGER', 'boolean' => $driver === 'pgsql' ? 'BOOLEAN' : 'TINYINT', 'text' => 'TEXT', 'datetime_immutable' => $driver === 'pgsql' ? 'TIMESTAMP(0)' : 'DATETIME', 'string' => 'VARCHAR(' . ($options['length'] ?? 255) . ')', default => throw new \InvalidArgumentException('不支持的字段类型。')
        };
    }

    private function default(mixed $value): string
    {
        return is_int($value) || is_float($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
