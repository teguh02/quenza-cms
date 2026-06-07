<?php
declare(strict_types=1);

namespace Quenza\Core\Database\Schema;

use Quenza\Core\Database\Connection;
use RuntimeException;

final class ForeignKeyDefinition
{
    private string $references = 'id';

    private ?string $onTable = null;

    private string $onDelete = 'RESTRICT';

    private string $onUpdate = 'RESTRICT';

    public function __construct(
        private readonly string $column,
        private readonly string $name,
    ) {
    }

    public function references(string $column): self
    {
        $this->references = $column;

        return $this;
    }

    public function on(string $table): self
    {
        $this->onTable = $table;

        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);

        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);

        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('CASCADE');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    public function nullOnUpdate(): self
    {
        return $this->onUpdate('SET NULL');
    }

    public function toSql(Connection $connection): string
    {
        if ($this->onTable === null) {
            throw new RuntimeException(sprintf('Foreign key %s belum memiliki tabel referensi.', $this->name));
        }

        return sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s',
            $connection->quoteIdentifier($this->name),
            $connection->quoteIdentifier($this->column),
            $connection->quotedTable($this->onTable),
            $connection->quoteIdentifier($this->references),
            $this->onDelete,
            $this->onUpdate,
        );
    }

    public function name(): string
    {
        return $this->name;
    }
}
