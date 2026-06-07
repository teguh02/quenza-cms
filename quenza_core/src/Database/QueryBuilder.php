<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use RuntimeException;

final class QueryBuilder
{
    /**
     * @var list<string>
     */
    private array $columns = ['*'];

    /**
     * @var list<array<string, mixed>>
     */
    private array $wheres = [];

    /**
     * @var list<array{column: string, direction: string}>
     */
    private array $orders = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $table,
    ) {
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? array_values($columns) : [$columns];

        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $operator = $value === null ? '=' : strtoupper((string) $operatorOrValue);
        $resolvedValue = $value === null ? $operatorOrValue : $value;

        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE'], true)) {
            throw new RuntimeException(sprintf('Operator where tidak didukung: %s', $operator));
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $resolvedValue,
        ];

        return $this;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function whereAll(array $attributes): self
    {
        foreach ($attributes as $column => $value) {
            if ($value === null) {
                $this->whereNull($column);

                continue;
            }

            $this->where($column, $value);
        }

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
        ];

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => array_values($values),
        ];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $resolvedDirection = strtoupper($direction);

        if (!in_array($resolvedDirection, ['ASC', 'DESC'], true)) {
            throw new RuntimeException(sprintf('Direction orderBy tidak didukung: %s', $direction));
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $resolvedDirection,
        ];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();

        return $this->database->select($sql, $bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $query = clone $this;
        $query->limit(1);

        $rows = $query->get();

        return $rows[0] ?? null;
    }

    public function count(): int
    {
        [$sql, $bindings] = $this->compileSelect(aggregate: 'COUNT(*)');

        return (int) $this->database->scalar($sql, $bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        if ($this->wheres === []) {
            throw new RuntimeException('Update query builder tanpa where tidak diizinkan.');
        }

        if ($values === []) {
            return 0;
        }

        $assignments = [];
        $bindings = [];
        $index = 0;

        foreach ($values as $column => $value) {
            $parameter = 'set_' . $index++;
            $assignments[] = sprintf('%s = :%s', $this->database->quoteIdentifier($column), $parameter);
            $bindings[$parameter] = $value;
        }

        [$whereSql, $whereBindings] = $this->compileWhere();

        return $this->database->statement(
            sprintf(
                'UPDATE %s SET %s WHERE %s',
                $this->database->quotedTable($this->table),
                implode(', ', $assignments),
                $whereSql,
            ),
            [...$bindings, ...$whereBindings],
        );
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new RuntimeException('Delete query builder tanpa where tidak diizinkan.');
        }

        [$whereSql, $whereBindings] = $this->compileWhere();

        return $this->database->statement(
            sprintf('DELETE FROM %s WHERE %s', $this->database->quotedTable($this->table), $whereSql),
            $whereBindings,
        );
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileSelect(?string $aggregate = null): array
    {
        $columns = $aggregate
            ?? implode(', ', array_map(
                fn (string $column): string => $column === '*' ? '*' : $this->database->quoteIdentifier($column),
                $this->columns,
            ));

        $sql = sprintf('SELECT %s FROM %s', $columns, $this->database->quotedTable($this->table));
        [$whereSql, $bindings] = $this->compileWhere();

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($aggregate === null && $this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', array_map(
                fn (array $order): string => sprintf(
                    '%s %s',
                    $this->database->quoteIdentifier($order['column']),
                    $order['direction'],
                ),
                $this->orders,
            ));
        }

        if ($aggregate === null && $this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($aggregate === null && $this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return [$sql, $bindings];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileWhere(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $fragments = [];
        $bindings = [];
        $index = 0;

        foreach ($this->wheres as $where) {
            $column = $this->database->quoteIdentifier((string) $where['column']);

            switch ($where['type']) {
                case 'basic':
                    $parameter = 'where_' . $index++;
                    $fragments[] = sprintf('%s %s :%s', $column, $where['operator'], $parameter);
                    $bindings[$parameter] = $where['value'];

                    break;

                case 'null':
                    $fragments[] = sprintf('%s IS NULL', $column);

                    break;

                case 'in':
                    $values = $where['values'];

                    if ($values === []) {
                        $fragments[] = '0 = 1';

                        break;
                    }

                    $placeholders = [];

                    foreach ($values as $value) {
                        $parameter = 'where_' . $index++;
                        $placeholders[] = ':' . $parameter;
                        $bindings[$parameter] = $value;
                    }

                    $fragments[] = sprintf('%s IN (%s)', $column, implode(', ', $placeholders));

                    break;
            }
        }

        return [implode(' AND ', $fragments), $bindings];
    }
}
