<?php
declare(strict_types=1);

namespace Quenza\Core\Database\Schema;

use Quenza\Core\Database\Connection;
use Quenza\Core\Database\DatabaseDriver;

final class ColumnDefinition
{
    /**
     * @param list<string> $allowedValues
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly array $allowedValues = [],
    ) {
    }

    public bool $nullable = false;

    public bool $primary = false;

    public bool $autoIncrement = false;

    public bool $unsigned = false;

    public bool $useCurrent = false;

    public bool $hasDefault = false;

    public mixed $defaultValue = null;

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function primary(bool $primary = true): self
    {
        $this->primary = $primary;

        return $this;
    }

    public function autoIncrement(bool $autoIncrement = true): self
    {
        $this->autoIncrement = $autoIncrement;

        return $this;
    }

    public function unsigned(bool $unsigned = true): self
    {
        $this->unsigned = $unsigned;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->defaultValue = $value;

        return $this;
    }

    public function useCurrent(): self
    {
        $this->useCurrent = true;

        return $this;
    }

    public function toSql(Connection $connection, bool $inlinePrimary = true): string
    {
        if ($connection->isSqlite() && $this->autoIncrement && $this->primary) {
            return sprintf('%s INTEGER PRIMARY KEY AUTOINCREMENT', $connection->quoteIdentifier($this->name));
        }

        $parts = [
            $connection->quoteIdentifier($this->name),
            $this->resolveType($connection->driver()),
        ];

        if (!$this->nullable) {
            $parts[] = 'NOT NULL';
        }

        if ($this->useCurrent) {
            $parts[] = 'DEFAULT CURRENT_TIMESTAMP';
        } elseif ($this->hasDefault) {
            $parts[] = 'DEFAULT ' . $this->literal($this->defaultValue);
        }

        if ($connection->isMysql() && $this->autoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
        }

        if ($inlinePrimary && $this->primary) {
            $parts[] = 'PRIMARY KEY';
        }

        if ($this->type === 'enum') {
            $parts[] = $this->enumConstraint($connection);
        }

        return implode(' ', $parts);
    }

    private function resolveType(DatabaseDriver $driver): string
    {
        return match ($this->type) {
            'id' => $driver === DatabaseDriver::Mysql ? 'BIGINT UNSIGNED' : 'INTEGER',
            'bigInteger' => $driver === DatabaseDriver::Mysql
                ? ('BIGINT' . ($this->unsigned ? ' UNSIGNED' : ''))
                : 'INTEGER',
            'integer' => $driver === DatabaseDriver::Mysql
                ? ('INT' . ($this->unsigned ? ' UNSIGNED' : ''))
                : 'INTEGER',
            'smallInteger' => $driver === DatabaseDriver::Mysql
                ? ('SMALLINT' . ($this->unsigned ? ' UNSIGNED' : ''))
                : 'INTEGER',
            'string' => sprintf('VARCHAR(%d)', $this->length ?? 255),
            'text' => 'TEXT',
            'longText' => $driver === DatabaseDriver::Mysql ? 'LONGTEXT' : 'TEXT',
            'boolean' => $driver === DatabaseDriver::Mysql ? 'TINYINT(1)' : 'INTEGER',
            'timestamp' => $driver === DatabaseDriver::Mysql ? 'TIMESTAMP' : 'TEXT',
            'enum' => $driver === DatabaseDriver::Mysql
                ? sprintf('VARCHAR(%d)', $this->length ?? $this->longestAllowedValue())
                : 'TEXT',
            default => throw new \RuntimeException(sprintf('Tipe kolom tidak didukung: %s', $this->type)),
        };
    }

    private function enumConstraint(Connection $connection): string
    {
        $values = implode(', ', array_map($this->literal(...), $this->allowedValues));

        return sprintf('CHECK (%s IN (%s))', $connection->quoteIdentifier($this->name), $values);
    }

    private function longestAllowedValue(): int
    {
        $lengths = array_map(static fn (string $value): int => strlen($value), $this->allowedValues);

        return max($lengths === [] ? [32] : $lengths);
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => sprintf("'%s'", str_replace("'", "''", (string) $value)),
        };
    }
}
