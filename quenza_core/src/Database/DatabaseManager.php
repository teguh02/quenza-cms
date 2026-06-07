<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use Closure;
use PDO;
use PDOStatement;
use RuntimeException;

final class DatabaseManager
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function driver(): DatabaseDriver
    {
        return $this->connection->driver();
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function tableName(string $table): string
    {
        return $this->connection->table($table);
    }

    public function quotedTable(string $table): string
    {
        return $this->connection->quotedTable($table);
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->connection->pdo()->prepare($sql);

        foreach ($bindings as $name => $value) {
            $parameter = is_int($name) ? $name + 1 : (str_starts_with($name, ':') ? $name : ':' . $name);

            $statement->bindValue($parameter, $value, $this->parameterType($value));
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $bindings = []): ?array
    {
        $row = $this->query($sql, $bindings)->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        return $this->query($sql, $bindings)->fetchColumn();
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(string $table, array $values): void
    {
        $this->insertGetId($table, $values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insertGetId(string $table, array $values): int
    {
        if ($values === []) {
            throw new RuntimeException('Insert membutuhkan minimal satu kolom.');
        }

        $columns = array_keys($values);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quotedTable($table),
            implode(', ', array_map($this->quoteIdentifier(...), $columns)),
            implode(', ', $placeholders),
        );

        $this->query($sql, $values);

        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $values, array $where): int
    {
        if ($values === []) {
            return 0;
        }

        if ($where === []) {
            throw new RuntimeException('Update tanpa kondisi tidak diizinkan.');
        }

        [$assignments, $assignmentBindings] = $this->compileAssignments($values);
        [$conditions, $conditionBindings] = $this->compileEqualityWhere($where);

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quotedTable($table),
            $assignments,
            $conditions,
        );

        return $this->statement($sql, [...$assignmentBindings, ...$conditionBindings]);
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new RuntimeException('Delete tanpa kondisi tidak diizinkan.');
        }

        [$conditions, $bindings] = $this->compileEqualityWhere($where);

        return $this->statement(
            sprintf('DELETE FROM %s WHERE %s', $this->quotedTable($table), $conditions),
            $bindings,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function updateOrInsert(string $table, array $attributes, array $values = []): bool
    {
        $existing = $this->table($table)->whereAll($attributes)->first();

        if ($existing === null) {
            $this->insert($table, [...$attributes, ...$values]);

            return true;
        }

        if ($values !== []) {
            $this->update($table, $values, $attributes);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function insertOrIgnore(string $table, array $attributes): bool
    {
        if ($this->table($table)->whereAll($attributes)->exists()) {
            return false;
        }

        $this->insert($table, $attributes);

        return true;
    }

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction(
            fn (): mixed => $callback($this),
        );
    }

    private function parameterType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * @param array<string, mixed> $values
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileAssignments(array $values): array
    {
        $fragments = [];
        $bindings = [];
        $index = 0;

        foreach ($values as $column => $value) {
            $parameter = 'set_' . $index++;
            $fragments[] = sprintf('%s = :%s', $this->quoteIdentifier($column), $parameter);
            $bindings[$parameter] = $value;
        }

        return [implode(', ', $fragments), $bindings];
    }

    /**
     * @param array<string, mixed> $where
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileEqualityWhere(array $where): array
    {
        $fragments = [];
        $bindings = [];
        $index = 0;

        foreach ($where as $column => $value) {
            if ($value === null) {
                $fragments[] = sprintf('%s IS NULL', $this->quoteIdentifier($column));

                continue;
            }

            $parameter = 'where_' . $index++;
            $fragments[] = sprintf('%s = :%s', $this->quoteIdentifier($column), $parameter);
            $bindings[$parameter] = $value;
        }

        return [implode(' AND ', $fragments), $bindings];
    }
}
