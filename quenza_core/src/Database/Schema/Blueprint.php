<?php
declare(strict_types=1);

namespace Quenza\Core\Database\Schema;

use Quenza\Core\Database\Connection;
use RuntimeException;

final class Blueprint
{
    /**
     * @var list<ColumnDefinition>
     */
    private array $columns = [];

    /**
     * @var list<array{name: string, columns: list<string>}>
     */
    private array $indexes = [];

    /**
     * @var list<array{name: string, columns: list<string>}>
     */
    private array $uniqueConstraints = [];

    /**
     * @var list<ForeignKeyDefinition>
     */
    private array $foreignKeys = [];

    /**
     * @var list<string>|null
     */
    private ?array $primaryColumns = null;

    private ?string $primaryName = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
        private readonly bool $altering = false,
    ) {
    }

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn(
            (new ColumnDefinition($name, 'id'))
                ->unsigned()
                ->primary()
                ->autoIncrement(),
        );
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned();
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'bigInteger'));
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'integer'));
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'smallInteger'));
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'string', $length));
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'text'));
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'longText'));
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'boolean'));
    }

    /**
     * @param list<string> $allowedValues
     */
    public function enum(string $name, array $allowedValues): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'enum', null, $allowedValues));
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'timestamp'));
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->useCurrent();
        $this->timestamp('updated_at')->useCurrent();
    }

    public function primary(array|string $columns, ?string $name = null): void
    {
        $this->primaryColumns = $this->normalizeColumns($columns);
        $this->primaryName = $name ?? $this->constraintName('pk', $this->primaryColumns);
    }

    public function unique(array|string $columns, ?string $name = null): void
    {
        $resolvedColumns = $this->normalizeColumns($columns);

        $this->uniqueConstraints[] = [
            'name' => $name ?? $this->constraintName('uq', $resolvedColumns),
            'columns' => $resolvedColumns,
        ];
    }

    public function index(array|string $columns, ?string $name = null): void
    {
        $resolvedColumns = $this->normalizeColumns($columns);

        $this->indexes[] = [
            'name' => $name ?? $this->constraintName('idx', $resolvedColumns),
            'columns' => $resolvedColumns,
        ];
    }

    public function foreign(string $column, ?string $name = null): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition(
            $column,
            $name ?? $this->constraintName('fk', [$column]),
        );

        $this->foreignKeys[] = $foreignKey;

        return $foreignKey;
    }

    /**
     * @return list<string>
     */
    public function toSql(): array
    {
        return $this->altering ? $this->compileAlterSql() : $this->compileCreateSql();
    }

    private function addColumn(ColumnDefinition $column): ColumnDefinition
    {
        $this->columns[] = $column;

        return $column;
    }

    /**
     * @return list<string>
     */
    private function compileCreateSql(): array
    {
        $definitions = array_map(
            fn (ColumnDefinition $column): string => $column->toSql(
                $this->connection,
                !$this->columnBelongsToCompositePrimary($column->name),
            ),
            $this->columns,
        );

        if ($this->primaryColumns !== null) {
            $definitions[] = sprintf(
                'CONSTRAINT %s PRIMARY KEY (%s)',
                $this->connection->quoteIdentifier((string) $this->primaryName),
                $this->quotedColumns($this->primaryColumns),
            );
        }

        foreach ($this->uniqueConstraints as $constraint) {
            $definitions[] = sprintf(
                'CONSTRAINT %s UNIQUE (%s)',
                $this->connection->quoteIdentifier($constraint['name']),
                $this->quotedColumns($constraint['columns']),
            );
        }

        foreach ($this->foreignKeys as $foreignKey) {
            $definitions[] = $foreignKey->toSql($this->connection);
        }

        $statements = [sprintf(
            "CREATE TABLE IF NOT EXISTS %s (\n    %s\n)",
            $this->connection->quotedTable($this->table),
            implode(",\n    ", $definitions),
        )];

        foreach ($this->indexes as $index) {
            $statements[] = sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $this->connection->quoteIdentifier($index['name']),
                $this->connection->quotedTable($this->table),
                $this->quotedColumns($index['columns']),
            );
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function compileAlterSql(): array
    {
        if ($this->foreignKeys !== []) {
            throw new RuntimeException('Penambahan foreign key pada mode alter belum didukung secara portable.');
        }

        if ($this->primaryColumns !== null) {
            throw new RuntimeException('Perubahan primary key pada mode alter belum didukung secara portable.');
        }

        $statements = [];

        foreach ($this->columns as $column) {
            if ($column->primary || $column->autoIncrement) {
                throw new RuntimeException('Menambah kolom auto increment atau primary key via alter belum didukung secara portable.');
            }

            $statements[] = sprintf(
                'ALTER TABLE %s ADD COLUMN %s',
                $this->connection->quotedTable($this->table),
                $column->toSql($this->connection, false),
            );
        }

        foreach ($this->uniqueConstraints as $constraint) {
            $statements[] = $this->createIndexStatement($constraint['name'], $constraint['columns'], true);
        }

        foreach ($this->indexes as $index) {
            $statements[] = $this->createIndexStatement($index['name'], $index['columns'], false);
        }

        return $statements;
    }

    /**
     * @param list<string> $columns
     */
    private function createIndexStatement(string $name, array $columns, bool $unique): string
    {
        $ifNotExists = $this->connection->isSqlite() ? ' IF NOT EXISTS' : '';

        return sprintf(
            'CREATE%s INDEX%s %s ON %s (%s)',
            $unique ? ' UNIQUE' : '',
            $ifNotExists,
            $this->connection->quoteIdentifier($name),
            $this->connection->quotedTable($this->table),
            $this->quotedColumns($columns),
        );
    }

    private function columnBelongsToCompositePrimary(string $column): bool
    {
        return $this->primaryColumns !== null
            && count($this->primaryColumns) > 1
            && in_array($column, $this->primaryColumns, true);
    }

    /**
     * @param list<string> $columns
     */
    private function quotedColumns(array $columns): string
    {
        return implode(', ', array_map($this->connection->quoteIdentifier(...), $columns));
    }

    /**
     * @param array<string>|string $columns
     * @return list<string>
     */
    private function normalizeColumns(array|string $columns): array
    {
        return is_array($columns) ? array_values($columns) : [$columns];
    }

    /**
     * @param list<string> $columns
     */
    private function constraintName(string $prefix, array $columns): string
    {
        $base = $prefix . '_' . $this->connection->table($this->table) . '_' . implode('_', $columns);

        if (strlen($base) <= 56) {
            return $base;
        }

        return substr($base, 0, 47) . '_' . substr(sha1($base), 0, 8);
    }
}
